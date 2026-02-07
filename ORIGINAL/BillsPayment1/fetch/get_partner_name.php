<?php
header('Content-Type: application/json');
include '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $partnerID = isset($input['partnerID']) ? $input['partnerID'] : '';
    $partnerID_kpx = isset($input['partnerID_kpx']) ? $input['partnerID_kpx'] : '';
    
    $response = ['partner_name' => null];
    
    if ($partnerID !== 'All') {
        $sql = "SELECT partner_name FROM masterdata.partner_masterfile WHERE partner_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $partnerID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $response['partner_name'] = $row['partner_name'];
        }
    } elseif ($partnerID_kpx !== 'All') {
        $sql = "SELECT partner_name FROM masterdata.partner_masterfile WHERE partner_id_kpx = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $partnerID_kpx);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $response['partner_name'] = $row['partner_name'];
        }
    } elseif ($partnerID === 'All' || $partnerID_kpx === 'All') {
        $response['partner_name'] = 'All';
    }
    
    echo json_encode($response);
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>