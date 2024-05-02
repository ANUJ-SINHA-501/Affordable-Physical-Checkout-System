// SignUp.php
<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

include '../db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $gst = mysqli_real_escape_string($conn, $_POST['gst']);
    $phno = mysqli_real_escape_string($conn, $_POST['phno']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); 

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(array("status" => "error", "message" => "Invalid email format"));
        exit();
    }

    if (!preg_match('/^\d{10}$/', $phno)) {
        echo json_encode(array("status" => "error", "message" => "Invalid phone number format"));
        exit();
    }

    if (!preg_match('/\d{2}[A-Z]{5}\d{4}[A-Z]{1}\d[Z]{1}[A-Z\d]{1}/', $gst)) {
        echo json_encode(array("status" => "error", "message" => "Invalid GST format"));
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO vendors (name, email, type, address, gst, phno, password, auth_token, auth_token_time) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL)");
    if ($stmt === false) {
        die("Error: " . htmlspecialchars($conn->error));
    }
    $stmt->bind_param("sssssss", $name, $email, $type, $address, $gst, $phno, $password);

    if ($stmt->execute() === TRUE) {
        $vendor_id = $stmt->insert_id;
        echo json_encode(array("status" => "success", "message" => "New record created successfully", "vendor_id" => $vendor_id));
    } else {
        if ($conn->errno == 1062) {
            echo json_encode(array("status" => "error", "message" => "Email or phone number already exists"));
        } else {
            echo json_encode(array("status" => "error", "message" => $stmt->error));
        }
    }
    $stmt->close();
}
?>
