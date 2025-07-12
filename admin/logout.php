<?php
session_start();
session_destroy();
header('Location: /finalProject/login.php');
exit();
?> 