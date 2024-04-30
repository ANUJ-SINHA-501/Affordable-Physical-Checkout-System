// Login.php
<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

include '../db_config.php';
require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT vendor_id, email, password FROM vendors WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        $hash = $row['password'];

        if (password_verify($password, $hash)) { 
            
            if (isset($_SESSION['otp_attempts']) && $_SESSION['otp_attempts'] >= 6 && time() - $_SESSION['last_attempt_time'] < 1800) {
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
            $_SESSION['otp_attempts'] = 0;
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
    $otp = $_GET['otp'];
    $email = $_SESSION['email'];
    $row = $_SESSION['row'];

    if ($otp == $_SESSION['otp']) {
        $_SESSION['login_user'] = $email;
        $_SESSION['vendor_id'] = $row['vendor_id']; 
        $_SESSION['csrf_token'] = bin2hex(random_bytes(8)); 

        date_default_timezone_set('Asia/Kolkata');

        $_SESSION['csrf_token_time'] = time(); 

        $csrf_token_time = date('Y-m-d H:i:s', $_SESSION['csrf_token_time']);

        $stmt = $conn->prepare("UPDATE vendors SET csrf_token = ?, csrf_token_time = ? WHERE email = ?");
        $stmt->bind_param("sss", $_SESSION['csrf_token'], $csrf_token_time, $email);
        $stmt->execute();

        echo json_encode(array("status" => "success", "message" => "Login successful", "csrf_token" => $_SESSION['csrf_token'], "vendor_id" => $_SESSION['vendor_id']));
    } else {
        
        $_SESSION['otp_attempts']++;
        $_SESSION['last_attempt_time'] = time();

        $remaining_attempts = 6 - $_SESSION['otp_attempts'];
        $message = "Invalid OTP. You have " . $remaining_attempts . " attempts remaining.";

        echo json_encode(array("status" => "error", "message" => $message));
    }
}
?>