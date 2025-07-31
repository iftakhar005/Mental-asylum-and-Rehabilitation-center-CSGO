<?php
session_start();
$_SESSION['test'] = 'hello';
echo $_SESSION['test']; 