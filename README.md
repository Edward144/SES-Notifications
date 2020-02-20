# SES-Notifications
A basic log of email status notifications are sent via Amazon SNS POST requests.

I don't expect anyone to find this useful, I am purely setting up this repository for my own reference and backup purposes.

# How it works

index.php should be setup as the HTTPS endpoint within Amazon SNS, when a POST request comes across the type of the request will be checked. Either for a SubscriptionConfirmation to accept a new topic subscription or a Notification.

If the type is a Notification then the JSON will be inserted into a database. 

Visiting index.php without a POST request will display a table of all notifications, with the json parsed and formatted. 

getInfo.php is accessed via AJAX request and includes the code to pull the JSON for a specific row ID within the database. jQuery then appends an absolute div to the body with all possible JSON information displayed.

refreshRows.php is also accessed via AJAX request every 5 seconds to pull in any new rows that have been added to the database, and prepends that information to the top of the log table.
