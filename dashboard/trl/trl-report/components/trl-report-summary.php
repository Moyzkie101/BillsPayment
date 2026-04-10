<?php
$partners = [];
$partnersSql = "
    SELECT DISTINCT
        TRIM(COALESCE(partner_id_kpx, '')) AS partner_id_kpx,
        TRIM(COALESCE(partner_name, '')) AS partner_name
    FROM mldb.subbiller
    WHERE COALESCE(TRIM(partner_id_kpx), '') <> ''
      AND COALESCE(TRIM(partner_name), '') <> ''
    ORDER BY partner_name ASC
";

$partnersRes = mysqli_query($conn, $partnersSql);
if ($partnersRes) {
    while ($p = mysqli_fetch_assoc($partnersRes)) {
        $pid = (string) ($p['partner_id_kpx'] ?? '');
        $pname = (string) ($p['partner_name'] ?? '');
        if ($pid !== '' && $pname !== '') {
            $partners[$pid] = $pname;
        }
    }
}

$selectedPartnerId = isset($_GET['partner_id']) ? trim((string) $_GET['partner_id']) : '';
if ($selectedPartnerId !== '' && !isset($partners[$selectedPartnerId])) {
    $selectedPartnerId = '';
}

$selectedPartnerName = $selectedPartnerId !== '' ? (string) $partners[$selectedPartnerId] : '';
$yearColumns = [];
$rowsBySubBiller = [];
$totalsByYear = [];
$grandTotal = 0.0;
$exportUrl = '';
if ($selectedPartnerId !== '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $exportUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $baseDir . '/controllers/trl-report-excel.php?partner_id=' . rawurlencode($selectedPartnerId);
}

if ($selectedPartnerId !== '') {
    $sql = "
        SELECT
            COALESCE(NULLIF(TRIM(s.sub_billers_name), ''), 'UNKNOWN BILLER') AS sub_biller_name,
            YEAR(t.transfer_datetime) AS report_year,
            SUM(COALESCE(t.amount, 0)) AS total_amount
        FROM mldb.trl t
        INNER JOIN mldb.subbiller s
            ON CAST(t.wrong_biller_id AS CHAR) = CAST(s.sub_billers_id AS CHAR)
                WHERE s.partner_id_kpx = ?
                    AND t.transfer_datetime IS NOT NULL
                    AND t.status IS NULL
        GROUP BY COALESCE(NULLIF(TRIM(s.sub_billers_name), ''), 'UNKNOWN BILLER'), YEAR(t.transfer_datetime)
        ORDER BY sub_biller_name ASC, report_year ASC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $selectedPartnerId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($r = $result->fetch_assoc()) {
                $subBiller = (string) ($r['sub_biller_name'] ?? 'UNKNOWN BILLER');
                $year = (int) ($r['report_year'] ?? 0);
                $amount = (float) ($r['total_amount'] ?? 0);

                if ($year <= 0) {
                    continue;
                }

                $yearColumns[$year] = true;

                if (!isset($rowsBySubBiller[$subBiller])) {
                    $rowsBySubBiller[$subBiller] = [
                        'name' => $subBiller,
                        'years' => [],
                        'total' => 0.0
                    ];
                }

                $rowsBySubBiller[$subBiller]['years'][$year] = $amount;
                $rowsBySubBiller[$subBiller]['total'] += $amount;

                if (!isset($totalsByYear[$year])) {
                    $totalsByYear[$year] = 0.0;
                }
                $totalsByYear[$year] += $amount;
                $grandTotal += $amount;
            }
        }
        $stmt->close();
    }
}

$yearColumns = array_keys($yearColumns);
sort($yearColumns);
ksort($rowsBySubBiller, SORT_NATURAL | SORT_FLAG_CASE);
?>

<div class="trl-summary-card">
    <div class="trl-summary-head">
        <h3>Summary Details</h3>
        <p>Choose a partner to view yearly received amounts for each biller mapped to that partner.</p>
    </div>

    <div class="trl-summary-filter-row">
        <form method="get" class="trl-summary-filters">
            <input type="hidden" name="mode" value="summary">
            <label for="partner_id">Partner</label>
            <select id="partner_id" name="partner_id" class="trl-summary-select" onchange="this.form.submit()">
                <option value="">Select Partner</option>
                <?php foreach ($partners as $pid => $pname): ?>
                    <option value="<?php echo htmlspecialchars($pid); ?>" <?php echo $selectedPartnerId === (string) $pid ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pname); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="trl-summary-actions">
            <a
                href="<?php echo htmlspecialchars($exportUrl !== '' ? $exportUrl : '#'); ?>"
                id="trlExportBtn"
                class="btn btn-danger trl-export-btn <?php echo $selectedPartnerId === '' ? 'is-disabled' : ''; ?>"
                data-partner="<?php echo htmlspecialchars($selectedPartnerId); ?>"
                data-partner-name="<?php echo htmlspecialchars($selectedPartnerName); ?>"
            >Export Excel</a>
        </div>
    </div>

    <?php if ($selectedPartnerId === ''): ?>
        <div class="trl-summary-empty">Choose a partner to generate the Summary report table.</div>
    <?php elseif (empty($rowsBySubBiller)): ?>
        <div class="trl-summary-empty">No TRL rows found for the selected partner.</div>
    <?php else: ?>
        <div class="trl-summary-title">
            <?php echo htmlspecialchars(strtoupper($selectedPartnerName) . ' SUB BILLERS'); ?>
        </div>

        <div class="trl-summary-table-wrap">
            <table class="trl-summary-table">
                <?php // explicit colgroup to ensure header/data column alignment ?>
                <colgroup>
                    <col class="col-name" />
                    <?php foreach ($yearColumns as $year): ?>
                        <col class="col-year" />
                    <?php endforeach; ?>
                    <col class="col-total" />
                </colgroup>
                <thead>
                    <tr>
                        <th class="partner-col-head">
                            <?php echo htmlspecialchars(strtoupper((string) $selectedPartnerName)); ?><br>
                            <span>SUB BILLERS</span>
                        </th>
                        <?php foreach ($yearColumns as $year): ?>
                            <th><?php echo htmlspecialchars((string) $year); ?></th>
                        <?php endforeach; ?>
                        <th>Total Receivable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rowsBySubBiller as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) $row['name']); ?></td>
                            <?php foreach ($yearColumns as $year): ?>
                                <?php $val = isset($row['years'][$year]) ? (float) $row['years'][$year] : null; ?>
                                <td class="amt"><?php echo $val !== null ? number_format($val, 2) : '-'; ?></td>
                            <?php endforeach; ?>
                            <td class="amt total"><?php echo number_format((float) $row['total'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th></th>
                        <?php foreach ($yearColumns as $year): ?>
                            <th class="amt"><?php echo isset($totalsByYear[$year]) ? number_format((float) $totalsByYear[$year], 2) : '-'; ?></th>
                        <?php endforeach; ?>
                        <th class="amt overall-total"><?php echo number_format((float) $grandTotal, 2); ?></th>
                    </tr>
                    <tr class="spacer-row">
                        <th colspan="<?php echo 1 + count($yearColumns); ?>"></th>
                        <th></th>
                    </tr>
                    <tr>
                        <?php $blankCount = count($yearColumns); ?>
                        <?php for ($i = 0; $i < $blankCount; $i++): ?>
                            <th></th>
                        <?php endfor; ?>
                        <th class="grand-label">Grand Total</th>
                        <th class="amt grand-total"><?php echo number_format((float) $grandTotal, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    var btn = document.getElementById('trlExportBtn');
    if (!btn) return;

    btn.addEventListener('click', function(e) {
        var partnerId = (btn.getAttribute('data-partner') || '').trim();
        var partnerName = (btn.getAttribute('data-partner-name') || 'selected partner').trim();
        var href = btn.getAttribute('href') || '#';

        if (!partnerId || href === '#') {
            e.preventDefault();
            if (window.Swal) {
                Swal.fire({
                    icon: 'info',
                    title: 'Select Partner First',
                    text: 'Please choose a partner before exporting the report.'
                });
            }
            return;
        }

        e.preventDefault();
        if (!window.Swal) {
            window.location.href = href;
            return;
        }

        Swal.fire({
            icon: 'question',
            title: 'Export Report?',
            html: 'Export Excel report for <b>' + String(partnerName)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;') + '</b>?',
            showCancelButton: true,
            confirmButtonText: 'Yes, Export',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                window.location.href = href;
            }
        });
    });
})();
</script>
