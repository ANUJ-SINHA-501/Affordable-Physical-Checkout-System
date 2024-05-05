<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

include '../db_config.php';
require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

session_start();

if (isset($_SESSION['last_service_request_time']) && (time() - $_SESSION['last_service_request_time']) >= 10800) {
    // Logout the user
    $vendor_id = $_SESSION['vendor_id'];
    $stmt = $conn->prepare("UPDATE vendors SET auth_token = NULL, auth_token_time = NULL WHERE vendor_id = ?");
    $stmt->bind_param("s", $vendor_id);
    $stmt->execute();
    
    if (session_status() == PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }

    echo json_encode(array("status" => "error", "message" => "You were inactive for 3 hours. Please login again."));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT vendor_id, email, password, otp_attempts, auth_token_time FROM vendors WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        $hash = $row['password'];

        if (password_verify($password, $hash)) { 
            if ($row['otp_attempts'] >= 6 && time() - strtotime($row['auth_token_time']) < 1800) {
                echo json_encode(array("status" => "error", "message" => "Too many failed attempts. Please try again after 30 minutes."));
                exit();
            }

            $otp = rand(100000, 999999);
            $hashed_otp = password_hash($otp, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE vendors SET otp = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashed_otp, $email);
            $stmt->execute();

            $mail = new PHPMailer\PHPMailer\PHPMailer;

            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; 
            $mail->SMTPAuth = true; 
            $mail->Username = 'anujsinha501@gmail.com'; 
            $mail->Password = 'dtzsdmgzexxmlsxc'; 
            $mail->SMTPSecure = 'tls'; 
            $mail->Port = 587; 

            $mail->setFrom('anujsinha501@gmail.com', 'Anuj Sinha');
            $mail->addAddress($email); 

            $mail->isHTML(true); 

            $mail->Subject = 'OTP for Login';
            $mail->Body    = 'The OTP to login to your POS account is: ' . $otp;

            if(!$mail->send()) {
                echo json_encode(array("status" => "error", "message" => "Mailer Error: " . $mail->ErrorInfo));
                exit();
            }

            echo json_encode(array("status" => "success", "message" => "OTP sent to email"));
        } else {
            echo json_encode(array("status" => "error", "message" => "Your Email or Password is invalid"));
        }
    } else {
        echo json_encode(array("status" => "error", "message" => "Email not found"));
    }
    $stmt->close();
} elseif ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['otp'], $_GET['email'])) {
        $otp = $_GET['otp'];
        $email = $_GET['email'];

        $stmt = $conn->prepare("SELECT otp, vendor_id, email, password, otp_attempts, auth_token_time FROM vendors WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row && password_verify($otp, $row['otp'])) {
            $_SESSION['login_user'] = $email;
            $_SESSION['vendor_id'] = $row['vendor_id'];
            $_SESSION['auth_token'] = bin2hex(random_bytes(8));
            date_default_timezone_set('Asia/Kolkata');
            $_SESSION['auth_token_time'] = time();
            $auth_token_time = date('Y-m-d H:i:s', $_SESSION['auth_token_time']);
            $stmt = $conn->prepare("UPDATE vendors SET auth_token = ?, auth_token_time = ?, otp_attempts = NULL, otp = NULL WHERE email = ?");
            $stmt->bind_param("sss", $_SESSION['auth_token'], $auth_token_time, $email);
            $stmt->execute();
            echo json_encode(array("status" => "success", "message" => "Login successful", "auth_token" => $_SESSION['auth_token'], "vendor_id" => $_SESSION['vendor_id']));
        } else {
            $stmt = $conn->prepare("UPDATE vendors SET otp_attempts = otp_attempts + 1 WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt = $conn->prepare("SELECT otp_attempts FROM vendors WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $remaining_attempts = 6 - $row['otp_attempts'];
            $message = "Invalid OTP. You have " . $remaining_attempts . " attempts remaining.";
            echo json_encode(array("status" => "error", "message" => $message));
        }
    } else {
        echo json_encode(array("status" => "error", "message" => "OTP or Email not provided."));
    }
}
elseif ($_SERVER["REQUEST_METHOD"] == "PUT") {
    $content_type = isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : null;
    if (strpos($content_type, "application/x-www-form-urlencoded") !== false) {
        parse_str(file_get_contents("php://input"), $data);
    } elseif (strpos($content_type, "application/json") !== false) {
        $data = json_decode(file_get_contents("php://input"), true);
    } else {
        echo json_encode(array("status" => "error", "message" => "Invalid content type"));
        exit();
    }
    
    if (!isset($data['auth_token']) || !isset($data['fields'])) {
        echo json_encode(array("status" => "error", "message" => "Authentication token and fields are required"));
        exit();
    }
    
    $stmt = $conn->prepare("SELECT vendor_id FROM vendors WHERE auth_token = ?");
    $stmt->bind_param("s", $data['auth_token']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, vendor could not be updated."));
        exit();
    }
    
    $vendor_id = $row['vendor_id'];
    
    $fields = $data['fields'];
    $allowed_fields = array("name", "email", "type", "address", "gst", "phno", "password"); // Add or remove field names as needed
    $update_fields = "";
    foreach ($fields as $key => $value) {
        if (!in_array($key, $allowed_fields)) {
            echo json_encode(array("status" => "error", "message" => "Invalid field name: " . $key));
            exit();
        }
        if ($key == "password") {
            $value = password_hash($value, PASSWORD_DEFAULT); // Hash the password before storing it
        }
        if ($update_fields != "") {
            $update_fields .= ", ";
        }
        $update_fields .= $key . " = '" . mysqli_real_escape_string($conn, $value) . "'";
    }

    $query = "UPDATE vendors SET " . $update_fields . " WHERE vendor_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $vendor_id);

    if ($stmt->execute() === TRUE) {
        echo json_encode(array("status" => "success", "message" => "Vendor details updated successfully"));
    } else {
        echo json_encode(array("status" => "error", "message" => $stmt->error));
    }
    $stmt->close();
}
?>