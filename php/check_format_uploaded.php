<?php
// check_format_uploaded.php
$filepath = '../uploads/format/APPROVAL OF TRANSACTION.docx';
if (file_exists($filepath)) {
    echo '1';
} else {
    echo '0';
}
