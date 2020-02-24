<?php require_once('databaseConnect.php'); ?>

<!DOCTYPE html>

<html>
    <head>
        <title>Amazon SES Notification Log</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="style.css" type="text/css" rel="stylesheet">
        
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    </head>
    
    <body>       
        <?php
            $jsonArray = json_decode(file_get_contents('php://input'), true);
             
            //Escape the backslashes so they aren't lost when inserting into database
            $jsonDb = file_get_contents('php://input');
            
            //Increment request counter, add new month if needed
            $currMonth = date('M_Y');
            $prevMonth = date('M_Y', strtotime('-1 Month', $currMonth));
        
            if($jsonArray['Type']) {                
                monthCheck:
                if($mysqli->query("SELECT counter FROM `requests_log` WHERE month = '{$currMonth}'")->num_rows == 1) {
                    $mysqli->query("UPDATE `requests_log` SET counter = (counter + 1) WHERE month = '{$currMonth}'");
                }
                else {
                    $mysqli->query("INSERT INTO `requests_log` (month) VALUES('{$currMonth}')");
                    goto monthCheck;
                }
            }
        
            //If request is a new subscription, accept it
            if($jsonArray['Type'] == 'SubscriptionConfirmation') {
                $subscribeUrl = $jsonArray['SubscribeURL'];

                //Visit the subscribe url to confirm
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $subscribeUrl);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_exec($ch);
                curl_close($ch);

                exit();
            }
            //If request is a notification, push it to the database
            elseif($jsonArray['Type'] == 'Notification' && $jsonArray['message']['notificationType'] != 'AmazonSnsSubscriptionSucceeded') {
                $insert = $mysqli->prepare("INSERT INTO `notifications_log` (json) VALUES(?)");
                $insert->bind_param('s', $jsonDb);
                $insert->execute();
                $insert->close();
                
                exit();
            }
            
            $limit = 1000;
            $offset = (isset($_GET['page']) && $_GET['page'] > 0 ? ($_GET['page'] * $limit) - $limit : 0);
            
            if($_GET['search']) {
                $search = '%' . $_GET['search'] . '%';
                
                $log = $mysqli->prepare("SELECT * FROM `notifications_log` WHERE SUBSTRING_INDEX(json, 'headers', 1) LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?");
                $log->bind_param('sii', $search, $limit, $offset);
                $log->execute();
                $log = $log->get_result();
                
                $numRows = $mysqli->prepare("SELECT * FROM `notifications_log` WHERE json LIKE ?");
                $numRows->bind_param('s', $search);
                $numRows->execute();
                $numRows = $numRows->get_result()->num_rows;
            }
            else {
                $log = $mysqli->prepare("SELECT * FROM `notifications_log` ORDER BY id DESC LIMIT ? OFFSET ?");
                $log->bind_param('ii', $limit, $offset);
                $log->execute();
                $log = $log->get_result();
                
                $numRows = $mysqli->query("SELECT * FROM `notifications_log`")->num_rows;
            }
        
            $pages = ceil($numRows / $limit);
            $pagination = '';
            $pre = ($_GET['search'] ? '&' : '?');
        
            if($pages > 1) {
                $pagination .= '<div class="pagination">';
                
                for($i = 1; $i <= $pages; $i++) {
                    $pagination .= '<a href="https://' . $_SERVER['SERVER_NAME'] . explode($pre . 'page', $_SERVER['REQUEST_URI'])[0] . $pre .'page=' . $i . '">' . $i . '</a>';
                }
                
                $pagination .= '</div>';
            }
        ?>
        <div class="main">
            <div class="requestCounter">
                <?php 
                    $cMonth = $mysqli->query("SELECT counter FROM `requests_log` WHERE month = '{$currMonth}'");
                    $cMonth = ($cMonth->num_rows > 0 ? $cMonth->fetch_array()[0] : 0);
                    $pMonth = $mysqli->query("SELECT counter FROM `requests_log` WHERE month = '{$prevMonth}' LIMIT 1");
                    $pMonth = ($cMonth->num_rows > 0 ? $cMonth->fetch_array()[0] : 0);
                ?>
                <h3>
                    <label>Requests this month:</label>
                    <span id="ajaxCounter"><?php echo $cMonth; ?> of 100,000</span>
                </h3>
                
                <h3>
                    <label>Requests last month:</label>
                    <span><?php echo $pMonth; ?> of 100,000</span>
                </h3>
            </div>
            
            <div class="content">
                <h1>Amazon SES Notifications Log</h1>
                <p>Click on the Message ID of any row to view more information about that message.</p>

                <form id="search">
                    <p>Anything you enter here will be searched across all columns below.</p>
                    <p>
                        You can enter % signs to find anything before or after that point. e.g:<br>
                        <code>user@%.com could return user@example.com, user@website.com, etc.<br>
                        %@example.com could return anyone with an email at example.com.</code>
                    </p>

                    <p>
                        <input type="text" name="search" value="<?php echo $_GET['search']; ?>">
                        <input type="button" name="doSearch" value="Search">
                        <input type="button" name="clear" value="Clear Search">
                    </p>
                </form>

                <ul class="legend">
                    <li class="delivery">Delivery</li>
                    <li class="bounce">Bounce</li>
                    <li class="complaint">Complaint</li>
                </ul>
            </div>
            
            <?php echo $pagination; ?>

            <?php if($log->num_rows > 0) : ?>
                <table id="logTable">
                    <thead>
                        <tr>
                            <th>Message ID</th>
                            <th>Sender</th>
                            <th>Sender IP</th>
                            <th>Sent Time</th>
                            <th>Recipient</th>
                            <th>Status Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while($row = $log->fetch_assoc()) : ?>
                            <?php 
                                $json = json_decode($row['json'], true); 
                                $message = json_decode($json['Message'], true);
                                $recipients = [];

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
                            ?>
                            <tr class="<?php foreach($message as $index => $value) { echo ($index != 'mail' && $index != 'notificationType' ? $index . ' ' : ''); }; ?>">
                                <td><a href="#" onclick="javascript: fullInfo(<?php echo $row['id']; ?>);"><?php echo (strlen($message['mail']['messageId']) > 20 ? substr($message['mail']['messageId'], 0, 20) . '...' : $message['mail']['messageId']); ?></a></td>
                                <td><?php echo $message['mail']['source']; ?></td>
                                <td><?php echo $message['mail']['sourceIp']; ?></td>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($message['mail']['timestamp'])); ?></td>
                                <td><?php echo implode(',<br>', $recipients); ?></td>
                                <td>
                                    <?php 
                                        echo ($message['delivery']['timestamp'] ?
                                                date('d/m/Y H:i:s', strtotime($message['delivery']['timestamp'])) : 
                                                ($message['bounce']['timestamp'] ?
                                                date('d/m/Y H:i:s', strtotime($message['bounce']['timestamp'])) :
                                                date('d/m/Y H:i:s', strtotime($message['complaint']['timestamp']))
                                                )
                                             ); 
                                    ?>
                                </td>
                                <td><?php echo $message['notificationType']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <h2>There are no notifications to display.</h2>
            <?php endif; ?>

            <?php echo $pagination; ?>

            <script>
                function fullInfo(id) {
                    $("body").find(".fullMessage").remove();

                    $.ajax({
                        url: 'getInfo.php',
                        method: 'GET',
                        dataType: 'json',
                        data: ({id}),
                        success: function(data) {
                            console.clear();
                            console.log(data);

                            var messageDetails = 'No information available';
                            var timestamp = '';

                            $(".main").css("overflow", "hidden");
                            $("body").append(`
                                <div class="fullOverlay"></div>
                            `);

                            if(data['notificationType'] == 'Bounce') {
                                var bouncedRecipients = '';

                                $.each(data['bounce']['bouncedRecipients'], function(index, item) {
                                    bouncedRecipients += `
                                        <tr>
                                            <td>` + item['emailAddress'] + `</td>
                                            <td>` + item['action'] + `</td>
                                            <td>` + item['status'] + `</td>
                                            <td>` + item['diagnosticCode'] + `</td>
                                        </tr>
                                    `;
                                });

                                timestamp = data['bounce']['timestamp'];
                                timestamp = timestamp.split("T")[0].split("-").reverse().join("/") + " " + timestamp.split("T")[1].split(".")[0];

                                messageDetails = `
                                    <p>
                                        <label>Bounce Type:</label>
                                        <span>` + data['bounce']['bounceType'] + ` | ` + data['bounce']['bounceSubType'] + `</span>
                                    </p>

                                    <p>
                                        <label>Timestamp:</label>
                                        <span>` + timestamp + `</span>
                                    </p>

                                    <p>
                                        <label>Mail Transfer Agent:</label>
                                        <span>` + data['bounce']['reportingMTA'] + ` | ` + data['bounce']['remoteMtaIp'] + `</span>
                                    </p>

                                    <p>
                                        <label>Feedback ID:</label>
                                        <span>` + data['bounce']['feedbackId'] + `</span>
                                    </p>

                                    <h3>Bounced From</h3>

                                    <table>
                                        <tr>
                                            <th>Email</th>
                                            <th>Action</th>
                                            <th>Status</th>
                                            <th>Diagnostics</th>
                                        <tr>
                                        ` + bouncedRecipients + `
                                    </table>
                                `;
                            }
                            else if(data['notificationType'] == 'Delivery') {
                                var deliveredRecipients = '';

                                $.each(data['delivery']['recipients'], function(index, item) {
                                    deliveredRecipients += `
                                        <tr>
                                            <td>` + item + `</td>
                                        </tr>
                                    `;
                                });

                                timestamp = data['delivery']['timestamp'];
                                timestamp = timestamp.split("T")[0].split("-").reverse().join("/") + " " + timestamp.split("T")[1].split(".")[0];

                                messageDetails = `
                                    <p>
                                        <label>Timestamp:</label>
                                        <span>` + timestamp + `</span>
                                    </p>

                                    <p>
                                        <label>Processing Time:</label>
                                        <span>` + data['delivery']['processingTimeMillis'] + `ms</span>
                                    </p>

                                    <p>
                                        <label>Mail Transfer Agent:</label>
                                        <span>` + data['delivery']['reportingMTA'] + ` | ` + data['delivery']['remoteMtaIp'] + `</span>
                                    </p>

                                    <p>
                                        <label>SMTP Response:</label>
                                        <span>` + data['delivery']['smtpResponse'] + `</span>
                                    </p>

                                    <h3>Delivered To</h3>

                                    <table>
                                        <tr>
                                            <th>Email</th>
                                        <tr>
                                        ` + deliveredRecipients + `
                                    </table>
                                `;
                            }
                            else if(data['notificationType'] == 'Complaint') {
                                var complaintRecipients = '';

                                $.each(data['complaint']['complainedRecipients'], function(index, item) {
                                    complaintRecipients += `
                                        <tr>
                                            <td>` + item['emailAddress'] + `</td>
                                        </tr>
                                    `;
                                });

                                timestamp = data['complaint']['timestamp'];
                                timestamp = timestamp.split("T")[0].split("-").reverse().join("/") + " " + timestamp.split("T")[1].split(".")[0];

                                messageDetails = `
                                    <p>
                                        <label>Complaint Type:</label>
                                        <span>` + data['complaint']['complaintFeedbackType'] + ` | ` + data['complaint']['complaintSubType'] + `</span>
                                    </p>

                                    <p>
                                        <label>Timestamp:</label>
                                        <span>` + timestamp + `</span>
                                    </p>

                                    <p>
                                        <label>User Agent:</label>
                                        <span>` + data['complaint']['userAgent'] + `</span>
                                    </p>

                                    <p>
                                        <label>Feedback ID:</label>
                                        <span>` + data['complaint']['feedbackId'] + `</span>
                                    </p>

                                    <h3>Complaints From</h3>

                                    <table>
                                        <tr>
                                            <th>Email</th>
                                        <tr>
                                        ` + complaintRecipients + `
                                    </table>
                                `;
                            }

                            //Append details for sender which appear in every notification type
                            var destinations = '';

                            $.each(data['mail']['destination'], function(index, item) {
                                destinations += `
                                    <tr>
                                        <td>` + item + `</td>
                                    </tr>
                                `;
                            });

                            timestamp = data['mail']['timestamp'];
                            timestamp = timestamp.split("T")[0].split("-").reverse().join("/") + " " + timestamp.split("T")[1].split(".")[0];

                            messageDetails += `
                                <hr>

                                <h2>Sender Details</h2>

                                <p>
                                    <label>Sender:</label>
                                    <span>` + data['mail']['source'] + ` | ` + data['mail']['sourceIp'] + `</span>
                                </p>

                                <p>
                                    <label>Timestamp:</label>
                                    <span>` + timestamp + `</span>
                                </p>

                                <p>
                                    <label>ARN:</label>
                                    <span>` + data['mail']['sourceArn'] + `</span>
                                </p>

                                <p>
                                    <label>Account ID:</label>
                                    <span>` + data['mail']['sendingAccountId'] + `</span>
                                </p>

                                <h3>Sent To</h3>

                                <table>
                                    <tr>
                                        <th>Email</th>
                                    </tr>
                                    ` + destinations + `
                                </table>
                            `;

                            //Append Headers
                            if(data['mail']['headers']) {
                                messageDetails += `
                                    <hr>

                                    <h2>Original headers</h2>

                                    <div class="codeBlock">
                                        <code>
                                            <label>Truncated:</label>
                                            <span>` + data['mail']['headersTruncated'] + `</span>
                                        </code>

                                        <h4>Headers</h4>
                                        <pre>` + JSON.stringify(data['mail']['headers'], null, 4) + `</pre>

                                        <h4>Common Headers</h4>
                                        <pre>` + JSON.stringify(data['mail']['commonHeaders'], null, 4) + `</pre>
                                    </div>
                                `;
                            }

                            $("body").append(`
                                <div class="fullMessage">
                                    <span id="close"><span>X</span></span>
                                    <h5>` + data['mail']['messageId'] + `</h5>
                                    <h2>` + data['notificationType'] + ` Details</h2>
                                    ` + messageDetails + `
                                </div>
                            `);
                        }
                    })
                }

                function closeFull() {
                    $(".fullMessage").remove();
                    $(".fullOverlay").remove();
                    $(".main").css("overflow", "");
                }

                $("body").on("click", ".fullMessage #close", closeFull);
                $("body").on("click", ".fullOverlay", closeFull);

                //Ajax to update rows            
                function refresh() {
                    if(window.location.href.indexOf("?") < 0) {
                        var latestRow = $("#logTable").find("a").first().attr("onclick").split("(")[1].split(")")[0];

                        $.ajax({
                            url: 'refreshRows.php',
                            method: 'GET',
                            dataType: 'json',
                            data: ({latestRow}),
                            success: function(data) {
                                $("#logTable tbody").prepend(data[0]);
                                $("#ajaxCounter").html(data[1] + " of 100,000");
                            }
                        });
                    }
                }

                var interval = setInterval(refresh, 5000);

                //Search functionality
                $("input[name='doSearch']").click(function() {
                    window.location.href = "?search=" + $("input[name='search']").val();
                });

                $("input[name='clear']").click(function() {
                    window.location.href = "/";
                });
            </script>
        </div>
    </body>
</html>

