<?php
session_start();
if ($_SESSION["lock"] != "1") {
  header("Location: /admin?err=Unauthorized Access!");
  die();
}