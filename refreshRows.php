<?php

    header('Content-type: application/json');

    require_once('databaseConnect.php');

    $newRows = $mysqli->prepare("SELECT * FROM `notifications_log` WHERE id > ?");
    $newRows->bind_param('i', $_GET['latestRow']);
    $newRows->execute();
    $newRows = $newRows->get_result();
    $data = [];

    if($newRows->num_rows > 0) {
        while($row = $newRows->fetch_assoc()) {
            $json = json_decode($row['json'], true);
            $message = json_decode($json['Message'], true);
            
            $recipients = [];
            $classes = [];
            
            if($message['notificationType'] == 'Delivery') {
                foreach($message['mail']['destination'] as $recipient) { 
                    array_push($recipients, $recipient); 
                }
            }
            elseif($message['notificationType'] == 'Bounce') {
                foreach($message['bounce']['bouncedRecipients'] as $recipient) { 
                    array_push($recipients, $recipient['emailAddress']); 
                }
            }
            elseif($message['notificationType'] == 'Complaint') {
                foreach($message['complaint']['complainedRecipients'] as $recipient) { 
                    array_push($recipients, $recipient['emailAddress']); 
                }
            }
            
            foreach($message as $index => $value) {  
                if($index != 'mail' && $index != 'notificationType') {
                    array_push($classes, $index);
                }
            }
            
            array_push($data,
                '<tr class="' . implode(' ', $classes) . '">' .
                    '<td><a href="#" onclick="javascript: fullInfo(' . $row['id'] . ');">' . (strlen($message['mail']['messageId']) > 20 ? substr($message['mail']['messageId'], 0, 20) . '...' : $message['mail']['messageId']) . '</a></td>' .
                    '<td>' . $message['mail']['source']. '</td>' .
                    '<td>' . $message['mail']['sourceIp'] . '</td>' .
                    '<td>' . date('d/m/Y H:i:s', strtotime($message['mail']['timestamp'])) . '</td>' .
                    '<td>' . implode(',<br>', $recipients) . '</td>' .
                    '<td>' . ($message['delivery']['timestamp'] ?
                        date('d/m/Y H:i:s', strtotime($message['delivery']['timestamp'])) : 
                        ($message['bounce']['timestamp'] ?
                        date('d/m/Y H:i:s', strtotime($message['bounce']['timestamp'])) :
                        date('d/m/Y H:i:s', strtotime($message['complaint']['timestamp']))
                        )
                     ) . '</td>' .
                    '<td>' . $message['notificationType'] . '</td>' .
                '</tr>'
            );
        }
        
        $currMonth = date('M_Y');
        $counter = $mysqli->query("SELECT counter FROM `requests_log` WHERE month = '{$currMonth}'");
        $counter = ($counter->num_rows == 1 ? $counter->fetch_array()[0] : 0);
        
        echo json_encode([implode($data), $counter]);
    }

?>