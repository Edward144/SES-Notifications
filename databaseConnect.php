<?php
    
    $mysqli = new mysqli('localhost', 'user', 'password', 'database');
    
    if($mysqli->connect_errno) {
        die($mysqli->connect_errno . ': ' . $mysqli->connect_error);
    }

    //Create relevant tables
    $mysqli->query("CREATE TABLE IF NOT EXISTS notifications_log(id INT auto_increment PRIMARY KEY, json JSON)");
    $mysqli->query("CREATE TABLE IF NOT EXISTS requests_log(id INT auto_increment PRIMARY KEY, month VARCHAR(255), counter INT DEFAULT 0)");
    
?>