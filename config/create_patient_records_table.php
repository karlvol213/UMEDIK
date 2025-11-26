<?php
require_once 'database.php';


$sql = file_get_contents('patient_records.sql');


if (mysqli_multi_query($conn, $sql)) {
    echo "Patient records tables created successfully.";
} else {
    echo "Error creating tables: " . mysqli_error($conn);
}


mysqli_close($conn);
?>