<?php
$source = 'C:/Users/Asus/.gemini/antigravity/brain/844f6b91-fa5e-44e1-80fa-03a739168e9e/pimt_login_bg_1777802044056.png';
$dest = 'img/login-bg.png';
if (copy($source, $dest)) {
    echo "Success!";
} else {
    echo "Failed.";
}
?>
