<?php
session_start();
$_SESSION['admin_logged_in'] = true;
include 'db.php';

$c1 = $conn->real_escape_string('<h5><i class="fa-solid fa-file-lines"></i> Portal</h5><p>Your premium platform for interesting articles, stories, and news. Stay tuned for exciting content updates!</p>');
$c2 = $conn->real_escape_string('<h5 class="text-center">Follow Us</h5><div class="social-links text-center"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-twitter"></i></a><a href="#"><i class="fa-brands fa-instagram"></i></a><a href="#"><i class="fa-brands fa-linkedin-in"></i></a></div>');
$c3 = $conn->real_escape_string('<h5 class="text-end">Quick Links</h5><ul class="list-unstyled text-end mb-0"><li><a href="./" class="text-white text-decoration-none">Home</a></li><li><a href="about/" class="text-white text-decoration-none">About Us</a></li><li><a href="contact/" class="text-white text-decoration-none">Contact Us</a></li></ul>');

$conn->query("UPDATE settings SET footer_col1='$c1', footer_col2='$c2', footer_col3='$c3' WHERE id=1");
echo "Done";
