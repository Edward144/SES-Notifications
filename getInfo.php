<?php
    
    header('Content-type: application/json');

    require_once('databaseConnect.php');

    $id = $_GET['id'];

    $json = $mysqli->prepare("SELECT json FROM `notifications_log` WHERE id = ?");
    $json->bind_param('i', $id);
    $json->execute();
    $json = $json->get_result();

    if($json->num_rows > 0) {
        $json = json_decode($json->fetch_array()[0], true);
        
        echo $json['Message'];
    }

?>