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

            $_SESSION['otp'] = $otp;
            $_SESSION['email'] = $email;
            $_SESSION['row'] = $row;

            echo json_encode(array("status" => "success", "message" => "OTP sent to email"));
        } else {
            echo json_encode(array("status" => "error", "message" => "Your Email or Password is invalid"));
        }
    } else {
        echo json_encode(array("status" => "error", "message" => "Email not found"));
    }
    $stmt->close();
} elseif ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_SESSION['otp'], $_SESSION['email'], $_SESSION['row'])) {
        $otp = $_GET['otp'];
        $email = $_SESSION['email'];
        $row = $_SESSION['row'];
        if ($otp == $_SESSION['otp']) {
            $_SESSION['login_user'] = $email;
            $_SESSION['vendor_id'] = $row['vendor_id'];
            $_SESSION['auth_token'] = bin2hex(random_bytes(8));
            date_default_timezone_set('Asia/Kolkata');
            $_SESSION['auth_token_time'] = time();
            $auth_token_time = date('Y-m-d H:i:s', $_SESSION['auth_token_time']);
            $stmt = $conn->prepare("UPDATE vendors SET auth_token = ?, auth_token_time = ?, otp_attempts = NULL WHERE email = ?");
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
        echo json_encode(array("status" => "error", "message" => "Session expired. Please login again."));
    }
}

?>