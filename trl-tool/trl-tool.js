// TRL Tool: generate sample TRL Excel files
(async function(){
  const dataRes = await fetch('trl-tool.json');
  const billers = await dataRes.json();
  // load branches for PAYMENT BRANCH fields
  let branches = [];
  try {
    const bres = await fetch('branch.json');
    branches = await bres.json();
    // normalize to ensure id and branch_name exist
    branches = branches.map(function(b, i){
      return {
        id: (b.branch_id !== undefined && b.branch_id !== null) ? b.branch_id : (b.id !== undefined && b.id !== null) ? b.id : (i+1),
        branch_name: b.branch_name || b.name || ('ML BRANCH ' + (i+1))
      };
    });
  } catch (e) {
    branches = [];
  }

  const dom = {
    rowsInput: document.getElementById('rowsCount'),
    generateBtn: document.getElementById('generateBtn'),
    tableBody: document.getElementById('sampleBody'),
    downloadBtn: document.getElementById('downloadBtn'),
    fileNameInput: document.getElementById('fileName'),
    billerSelect: document.getElementById('billerSelect'),
    billerList: document.getElementById('billerList')
  };
  // allowed Type of Request values
  const allowedTypes = [
    'NO PAYMENT RECEIVED',
    'DOUBLE POSTING',
    'WRONG BILLER',
    'OVERSTATED AMOUNT',
    'CANCELLED TRANSACTION',
    'TRIPLE POSTING',
    'UNREFLECTED TRXN'
  ];

  function randInt(min,max){return Math.floor(Math.random()*(max-min+1))+min}
  function pick(arr){return arr[randInt(0,arr.length-1)]}
  function randAmount(){return randInt(50,999999)}
  function pad(n,len){return String(n).padStart(len,'0')}
  function maybe(prob){return Math.random() < prob}

  const firstNames = ['JUAN','MARIA','PEDRO','ANA','JOSE','LUISA','MARK','ALTHEA','JOHN','PAULO','ANGELA','ROMEO'];
  const lastNames = ['DELA CRUZ','SANTOS','REYES','GARCIA','MENDOZA','RAMOS','AQUINO','CASTILLO','NAVARRO','FLORES'];
  const branchPrefixes = ['ML BRANCH','ML EXPRESS','ML WALLET HUB','ML PAY CENTER'];

  let generationSeq = 0;
  let generatedRefs = new Set();
  let generatedAccounts = new Set();

  function randomRef(){
    // collision-safe ref generation for each Generate click
    var attempts = 0;
    while (attempts < 1000) {
      attempts++;
      const base = Date.now().toString().slice(-9);
      const seq = pad(generationSeq++, 4);
      const noise = pad(randInt(0, 99999), 5);
      const ref = 'BPP' + base + seq + noise;
      if (!generatedRefs.has(ref)) {
        generatedRefs.add(ref);
        return ref;
      }
    }

    // hard fallback (practically unreachable)
    const forced = 'BPP' + Date.now() + pad(generationSeq++, 6);
    generatedRefs.add(forced);
    return forced;
  }

  function randomAccountNo(){
    // 13-digit unique-ish account number for each batch
    var attempts = 0;
    while (attempts < 1000) {
      attempts++;
      const acct = pad(randInt(100000000000, 9999999999999), 13);
      if (!generatedAccounts.has(acct)) {
        generatedAccounts.add(acct);
        return acct;
      }
    }
    const forced = pad(Date.now() % 10000000000000, 13);
    generatedAccounts.add(forced);
    return forced;
  }

  function randomCustomerName(){
    return pick(firstNames) + ' ' + pick(lastNames);
  }

  function formatDate(d){
    // example format: 2023-03-15 03:04:44 PM
    const yyyy = d.getFullYear();
    const mm = pad(d.getMonth()+1,2);
    const dd = pad(d.getDate(),2);
    let hh = d.getHours();
    const ampm = hh>=12? 'PM':'AM';
    hh = hh%12; if (hh===0) hh=12;
    const hhs = pad(hh,2);
    const mins = pad(d.getMinutes(),2);
    const secs = pad(d.getSeconds(),2);
    return `${yyyy}-${mm}-${dd} ${hhs}:${mins}:${secs} ${ampm}`;
  }

  // currently selected wrong biller; null => use random per-row (All)
  var selectedWrongBiller = null;

  function makeRow(){
    var wrong;
    if (selectedWrongBiller && selectedWrongBiller.id) {
      wrong = selectedWrongBiller;
    } else {
      wrong = pick(billers);
    }
    // pick a different correct biller sometimes
    let correct = pick(billers);
    if (correct.id === wrong.id) {
      correct = billers[(billers.indexOf(wrong)+1) % billers.length];
    }

    var branchId, branchName;
    if (branches && branches.length) {
      var b = pick(branches);
      branchId = b.id;
      branchName = b.branch_name;
    } else {
      branchId = randInt(1, 999);
      branchName = pick(branchPrefixes) + ' ' + randInt(1, 400);
    }

    // choose type and build reason accordingly
    const type = pick(allowedTypes);

    // helper to format currency like 11,092.00
    function fmtAmt(v){
      return Number(v).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    }

    // default amounts
    let amount = randAmount();
    let reason = '';

    switch(type) {
      case 'NO PAYMENT RECEIVED':
        reason = pick([
          'NO PAYMENT RECEIVED FROM THE CUSTOMER',
          'CUSTOMER DID NOT COMPLETE PAYMENT',
          'TRANSACTION ATTEMPTED BUT NO PAYMENT RECEIVED'
        ]);
        break;
      case 'DOUBLE POSTING':
        reason = pick([
          'DOUBLE POSTING',
          'TRANSACTION POSTED TWICE',
          'DUPLICATE POSTING OF SAME PAYMENT'
        ]);
        break;
      case 'WRONG BILLER':
        // state intended correct biller in reason
        reason = pick([
          'WRONG BILLER - INTENDED FOR ' + correct.name,
          'WRONG BILLER. SHOULD BE POSTED TO ' + correct.name,
          'MISROUTED BILLER; INTENDED BILLER IS ' + correct.name
        ]);
        break;
      case 'OVERSTATED AMOUNT':
        // make amount overstated vs correct
        const correctAmt = Math.max(50, amount - randInt(1000, 50000));
        const diff = amount - correctAmt;
        reason = pick([
          'OVERSTATED AMOUNT PHP ' + fmtAmt(amount) + ' INSTEAD OF PHP ' + fmtAmt(correctAmt) + ' WITH THE DIFFERENCE OF PHP ' + fmtAmt(diff),
          'OVERPOSTED AMOUNT: PHP ' + fmtAmt(amount) + ' SHOULD BE PHP ' + fmtAmt(correctAmt) + ' (DIFF PHP ' + fmtAmt(diff) + ')'
        ]);
        break;
      case 'UNREFLECTED TRXN':
        reason = pick([
          'UNREFLECTED TXN TO ML REPORT',
          'TRANSACTION NOT REFLECTED IN ML REPORT',
          'POSTED PAYMENT IS UNREFLECTED IN REPORTING'
        ]);
        break;
      case 'TRIPLE POSTING':
        reason = pick([
          'TRIPLE POSTING',
          'TRANSACTION POSTED THREE TIMES',
          'TRIPLE ENTRY OF SAME PAYMENT'
        ]);
        break;
      case 'CANCELLED TRANSACTION':
        // wrong posted amount smaller or larger with variant reasons
        const posted = Math.max(50, randInt(100, 99999));
        const adjustment = randInt(100, 20000);
        const correctedHigher = maybe(0.5);
        const correctAmt2 = correctedHigher ? (posted + adjustment) : Math.max(50, posted - adjustment);
        amount = posted;
        reason = pick([
          'CANCELLED TRANSACTION - Wrong amount posted (' + fmtAmt(posted) + ') instead of (' + fmtAmt(correctAmt2) + ')',
          'CANCELLED TRANSACTION WRONG AMOUNT ENCODED (' + fmtAmt(posted) + ') instead of (' + fmtAmt(correctAmt2) + ')'
        ]);
        break;
      default:
        reason = 'INTENDED FOR ' + correct.name;
    }

    return {
      'TRANS. DATE/TIME': formatDate(new Date(Date.now() - randInt(0,60*60*24*365)*1000)),
      'REF. NO.': randomRef(),
      'WRONG BILLER ID': wrong.id,
      'BILLER NAME': wrong.name,
      'ACCOUNT NO.': randomAccountNo(),
      'NAME': randomCustomerName(),
      'PAYMENT BRANCH ID': branchId,
      'PAYMENT BRANCH': branchName,
      'AMOUNT': amount,
      'TYPE OF REQUEST': type,
      'CORRECT BILLER ID': correct.id,
      'CORRECT BILLER NAME': correct.name,
      'REASON': reason
    };
  }

  function renderTable(rows){
    dom.tableBody.innerHTML = '';
    rows.forEach(r=>{
      const tr = document.createElement('tr');
      Object.keys(r).forEach(k=>{
        const td = document.createElement('td');
        td.textContent = r[k];
        tr.appendChild(td);
      });
      dom.tableBody.appendChild(tr);
    });
  }

  function exportXLSX(rows, filename){
    const ws_data = [];
    if (rows.length === 0) return;
    ws_data.push(Object.keys(rows[0]));
    rows.forEach(r=>{ ws_data.push(Object.values(r)); });
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    XLSX.utils.book_append_sheet(wb, ws, 'TRL');
    const wbout = XLSX.write(wb, {bookType:'xlsx', type:'array'});
    const blob = new Blob([wbout], {type:'application/octet-stream'});
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename || ('trl-sample.xlsx');
    document.body.appendChild(link);
    link.click();
    link.remove();
  }

  let currentRows = [];

  dom.generateBtn.addEventListener('click', function(){
    const n = Math.max(1, Math.min(10000, parseInt(dom.rowsInput.value) || 100));
    // reset uniqueness maps every generation batch
    generatedRefs = new Set();
    generatedAccounts = new Set();
    generationSeq = 0;
    const rows = [];
    for (let i=0;i<n;i++){ rows.push(makeRow()); }
    currentRows = rows;
    renderTable(rows.slice(0,500));
    const genEl = document.getElementById('generatedCount');
    if (genEl) genEl.textContent = n;
    dom.downloadBtn.disabled = false;
  });

  // populate biller datalist and wire selection behavior
  function sanitizeFileName(name) {
    return name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
  }

  // Fill datalist options (include an 'All' pseudo-option as placeholder)
  (function populateBillers(){
    var optAll = document.createElement('option');
    optAll.value = 'All';
    dom.billerList.appendChild(optAll);
    billers.forEach(function(b){
      var o = document.createElement('option');
      // display as "<id> - <name>" so user can find by id or name
      o.value = b.id + ' - ' + b.name;
      dom.billerList.appendChild(o);
    });
    // default placeholder
    dom.billerSelect.value = 'All';
    selectedWrongBiller = null;
    dom.billerSelect.addEventListener('input', function(e){
      var v = (e.target.value || '').trim();
      if (!v || v.toLowerCase() === 'all') {
        selectedWrongBiller = null;
        dom.fileNameInput.value = 'trl-sample-all.xlsx';
        return;
      }

      // try to parse id at start like "123 - NAME"
      var m = v.match(/^\s*(\d+)\s*-\s*(.+)$/);
      if (m) {
        var id = parseInt(m[1],10);
        var found = billers.find(function(b){ return Number(b.id) === id; });
        if (found) {
          selectedWrongBiller = found;
          dom.fileNameInput.value = 'trl-sample-' + sanitizeFileName(found.name) + '.xlsx';
          return;
        }
      }

      // try to match by name (case-insensitive contains)
      var foundByName = billers.find(function(b){ return b.name.toLowerCase().indexOf(v.toLowerCase()) !== -1; });
      if (foundByName) {
        selectedWrongBiller = foundByName;
        dom.fileNameInput.value = 'trl-sample-' + sanitizeFileName(foundByName.name) + '.xlsx';
        return;
      }

      // fallback to All
      selectedWrongBiller = null;
      dom.fileNameInput.value = 'trl-sample-all.xlsx';
    });
  })();

  dom.downloadBtn.addEventListener('click', function(){
    const fname = dom.fileNameInput.value.trim() || ('trl-sample-' + new Date().toISOString().slice(0,10) + '.xlsx');
    exportXLSX(currentRows, fname);
  });

})();
