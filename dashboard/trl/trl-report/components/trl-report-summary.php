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
        <h3>Summary Mode</h3>
        <p>Select a partner to view yearly receivables by mapped sub-biller.</p>
    </div>

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

    <?php if ($selectedPartnerId === ''): ?>
        <div class="trl-summary-empty">Choose a partner to generate the Summary report table.</div>
    <?php elseif (empty($rowsBySubBiller)): ?>
        <div class="trl-summary-empty">No TRL rows found for the selected partner.</div>
    <?php else: ?>
        <div class="trl-summary-title">
            <?php echo htmlspecialchars(strtoupper($selectedPartnerName) . ' BILLERS'); ?>
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
                            <span>BILLERS</span>
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
                        <th class="amt"><?php echo number_format((float) $grandTotal, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
