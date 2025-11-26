<?php
require_once 'database.php';

// Alter table to update column definitions with proper default values
$alterTableSQL = "ALTER TABLE users 
    MODIFY COLUMN address TEXT DEFAULT 'Not specified',
    MODIFY COLUMN department VARCHAR(255) DEFAULT 'Not specified',
    MODIFY COLUMN birthday DATE DEFAULT NULL,
    MODIFY COLUMN sex VARCHAR(20) DEFAULT 'Not specified';";

if (mysqli_query($conn, $alterTableSQL)) {
    // Update existing NULL values to 'Not specified'
    $updateSQL = "UPDATE users 
                 SET address = COALESCE(address, 'Not specified'),
                     department = COALESCE(department, 'Not specified'),
                     sex = COALESCE(sex, 'Not specified')
                 WHERE address IS NULL 
                    OR department IS NULL 
                    OR sex IS NULL;";
                    
    if (mysqli_query($conn, $updateSQL)) {
        echo "Successfully updated default values.<br>";
    } else {
        echo "Error updating default values: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "Error altering table: " . mysqli_error($conn) . "<br>";
}

// Update the display function to properly handle date format
mysqli_query($conn, "SET sql_mode = '';"); // This allows NULL in date columns

echo "Update completed.<br>";
?>