# SES-Notifications
A basic log of email status notifications are sent via Amazon SNS POST requests.

I don't expect anyone to find this useful, I am purely setting up this repository for my own reference.

# How it works

index.php should be setup as the HTTPS endpoint within Amazon SNS, when a POST request comes across the type of the request will be checked. Either for a SubscriptionConfirmation to accept a new topic subscription or a Notification.

If the type is a Notification then the JSON will be inserted into a database. 

Visiting index.php without a POST request will display a table of all notifications, with the json parsed and formatted. 

getInfo.php is accessed via AJAX request and includes the code to pull the JSON for a specific row ID within the database. jQuery then appends an absolute div to the body with all possible JSON information displayed.

refreshRows.php is also accessed via AJAX request every 5 seconds to pull in any new rows that have been added to the database, and prepends that information to the top of the log table.

# Database

After setting up the SNS endpoint all you need to do is create a database and enter the correct details into databaseConnect.php
The only table needed by the database is `notifications_log` with an auto increment "id" column and a json type "json" column

The `notifications_log` table is checked for and automatically added with the correct columns, via databaseConnect.php

# Console Log

When clicking any Message ID the developer console is cleared and the full JSON for that message is logged to the console. This is to cover showing any potential JSON that I am not aware of or that could be added in future, which will not be shown by the full message pop up.