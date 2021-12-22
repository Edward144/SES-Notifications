<?php

    require_once('databaseConnect.php'); 

    if($_SERVER['HTTP_USER_AGENT'] == 'Amazon Simple Notification Service Agent') {
        $jsonArray = json_decode(file_get_contents('php://input'), true);

        //Escape the backslashes so they aren't lost when inserting into database
        $jsonDb = file_get_contents('php://input');

        //If request is a new subscription, accept it
        if($jsonArray['Type'] == 'SubscriptionConfirmation') {
            $subscribeUrl = $jsonArray['SubscribeURL'];

            //Visit the subscribe url to confirm
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $subscribeUrl);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_exec($ch);
            curl_close($ch);
        }
        //If request is a notification, push it to the database
        elseif($jsonArray['Type'] == 'Notification' && $jsonArray['message']['notificationType'] != 'AmazonSnsSubscriptionSucceeded') {
            $idToCheck = '%' . $jsonArray['MessageId'] . '%';
            $checkId = $mysqli->prepare("SELECT COUNT(*) FROM `notifications_log` WHERE json LIKE ?");
            $checkId->bind_param('s', $idToCheck);
            $checkId->execute();
            $checkResult = $checkId->get_result()->fetch_array()[0];
            
            if($checkResult <= 0) {
                $insert = $mysqli->prepare("INSERT INTO `notifications_log` (json) VALUES(?)");
                $insert->bind_param('s', $jsonDb);
                $insert->execute();
                $insert->close();

                //Increment request counter, add new month if needed
                $currMonth = date('M_Y');
                $prevMonth = date('M_Y', strtotime('-1 Month'));

                if($mysqli->query("SELECT counter FROM `requests_log` WHERE month = '{$currMonth}'")->num_rows == 1) {
                    $mysqli->query("UPDATE `requests_log` SET counter = (counter + 1) WHERE month = '{$currMonth}'");
                }
                else {
                    $mysqli->query("INSERT INTO `requests_log` (month) VALUES('{$currMonth}')");
                    $mysqli->query("UPDATE `requests_log` SET counter = (counter + 1) WHERE month = '{$currMonth}'");
                }
            }
        }
    }
    else {
        echo $_SERVER['HTTP_USER_AGENT'];
    }

?>