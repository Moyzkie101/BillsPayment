<?php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $partnerName = $input['partnerName'] ?? '';
    
    if (!empty($partnerName) && $partnerName !== 'All') {
        $stmt = $conn->prepare("SELECT partner_id, partner_id_kpx FROM masterdata.partner_masterfile WHERE partner_name = ? LIMIT 1");
        $stmt->bind_param("s", $partnerName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode([
                'partner_id' => $row['partner_id'],
                'partner_id_kpx' => $row['partner_id_kpx']
            ]);
        } else {
            echo json_encode([
                'partner_id' => null,
                'partner_id_kpx' => null
            ]);
        }
        $stmt->close();
    } else {
        echo json_encode([
            'partner_id' => null,
            'partner_id_kpx' => null
        ]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>