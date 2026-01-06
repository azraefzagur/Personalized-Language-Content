<?php
require '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login kontrolü (student + admin olabilir)
if (!isset($_SESSION["user_id"])) {
    header("Location: /english_platform/auth/login.php");
    exit;
}

$message = "";

// HATA GONDER
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);

    if ($title !== "" && $description !== "") {
        $stmt = $pdo->prepare(
            "INSERT INTO error_reports (user_id, title, description)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$_SESSION["user_id"], $title, $description]);
        $message = "Error report sent successfully.";
    } else {
        $message = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Error Report</title>
    <link rel="stylesheet" href="/english_platform/assets/css/style.css">
</head>
<body>

<h2>Report an Error</h2>

<form method="POST">
    <input type="text" name="title" placeholder="Error title" required><br><br>
    <textarea name="description" rows="4" cols="50" placeholder="Describe the problem..." required></textarea><br><br>
    <button type="submit">Send Report</button>
</form>

<p><?php echo $message; ?></p>

</body>
</html>
