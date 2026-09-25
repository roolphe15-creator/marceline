<?php
session_start();

/* =========================================================
   ESPACE MARINA - SITE COMPLET
   HTML + CSS + JAVASCRIPT + PHP + MYSQL
   ========================================================= */

/* =========================
   CONFIGURATION
   ========================= */

$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "espace_marina";

$ADMIN_EMAIL = "admin@espacemarina.com";
$ADMIN_PASSWORD = "password";

$restaurantName = "Espace Marina";
$address = "Agoè Légbassito, en face du von Colonel Bali, Lomé, Togo";

$phone1 = "+228 71 10 29 29";
$phone2 = "+228 71 10 19 19";

$whatsapp = "22871102929";


/* =========================
   CONNEXION MYSQL
   ========================= */

try {

    $pdo = new PDO(
        "mysql:host=$DB_HOST;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    /* Création automatique de la base */
    $pdo->exec("
        CREATE DATABASE IF NOT EXISTS `$DB_NAME`
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci
    ");

    $pdo->exec("USE `$DB_NAME`");


    /* =========================
       TABLE RESERVATIONS
       ========================= */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reservations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(120) NOT NULL,
            telephone VARCHAR(40) NOT NULL,
            email VARCHAR(150),
            date_reservation DATE NOT NULL,
            heure TIME NOT NULL,
            personnes INT NOT NULL,
            service VARCHAR(100) DEFAULT 'Restaurant',
            message TEXT,
            statut VARCHAR(30) DEFAULT 'En attente',
            confirmation_envoyee TINYINT(1) DEFAULT 0,
            notification_date DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");


    /* =========================
       TABLE AVIS
       ========================= */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS avis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(120) NOT NULL,
            email VARCHAR(150),
            note INT NOT NULL,
            commentaire TEXT NOT NULL,
            statut VARCHAR(30) DEFAULT 'En attente',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");


    /* =========================
       TABLE MESSAGES
       ========================= */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(120) NOT NULL,
            email VARCHAR(150),
            telephone VARCHAR(40),
            message TEXT NOT NULL,
            lu TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");


    /* =========================
       MIGRATION AUTOMATIQUE
       ========================= */

    $check = $pdo->query(
        "SHOW COLUMNS FROM reservations LIKE 'service'"
    )->fetch();

    if (!$check) {
        $pdo->exec("
            ALTER TABLE reservations
            ADD COLUMN service VARCHAR(100)
            DEFAULT 'Restaurant'
            AFTER personnes
        ");
    }


    $check = $pdo->query(
        "SHOW COLUMNS FROM reservations LIKE 'confirmation_envoyee'"
    )->fetch();

    if (!$check) {
        $pdo->exec("
            ALTER TABLE reservations
            ADD COLUMN confirmation_envoyee TINYINT(1)
            DEFAULT 0
            AFTER statut
        ");
    }


    $check = $pdo->query(
        "SHOW COLUMNS FROM reservations LIKE 'notification_date'"
    )->fetch();

    if (!$check) {
        $pdo->exec("
            ALTER TABLE reservations
            ADD COLUMN notification_date DATETIME NULL
            AFTER confirmation_envoyee
        ");
    }

} catch (PDOException $e) {

    die("
        <div style='
            font-family:Arial;
            padding:30px;
            color:#fff;
            background:#111;
        '>
            <h2>Erreur de connexion</h2>
            <p>" .
            htmlspecialchars($e->getMessage()) .
            "</p>
        </div>
    ");
}


/* =========================================================
   FONCTIONS
   ========================================================= */

function redirectTo($url)
{
    header("Location: $url");
    exit;
}


/* =========================
   NORMALISATION TELEPHONE
   ========================= */

function normalizeTogoPhone($phone)
{
    $digits = preg_replace('/\D+/', '', $phone);

    if (substr($digits, 0, 5) === "00228") {
        $digits = substr($digits, 2);
    }

    if (substr($digits, 0, 3) === "228") {
        return $digits;
    }

    if (strlen($digits) === 8) {
        return "228" . $digits;
    }

    return $digits;
}


/* =========================
   MESSAGE CONFIRMATION
   ========================= */

function getConfirmationMessage($reservation)
{
    $date = date(
        "d/m/Y",
        strtotime($reservation["date_reservation"])
    );

    $heure = date(
        "H:i",
        strtotime($reservation["heure"])
    );

    return
        "Bonjour " .
        $reservation["nom"] .
        ",\n\n" .

        "Votre réservation à Espace Marina est confirmée ! ✅\n\n" .

        "📅 Date : " .
        $date .
        "\n" .

        "🕐 Heure : " .
        $heure .
        "\n" .

        "👥 Personnes : " .
        $reservation["personnes"] .
        "\n" .

        "🍽️ Service : " .
        $reservation["service"] .
        "\n\n" .

        "📍 Espace Marina\n" .
        "Agoè Légbassito, en face du von Colonel Bali, Lomé, Togo\n\n" .

        "📞 +228 71 10 29 29\n" .
        "📞 +228 71 10 19 19\n\n" .

        "Merci pour votre confiance et à bientôt chez Espace Marina !";
}


/* =========================
   EMAIL
   ========================= */

function sendConfirmationEmail($reservation)
{
    if (
        empty($reservation["email"]) ||
        !filter_var(
            $reservation["email"],
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $to = $reservation["email"];

    $subject =
        "Confirmation de votre réservation - Espace Marina";

    $message = getConfirmationMessage($reservation);

    $headers = [];

    $headers[] =
        "From: Espace Marina <admin@espacemarina.com>";

    $headers[] =
        "Reply-To: admin@espacemarina.com";

    $headers[] =
        "Content-Type: text/plain; charset=UTF-8";

    return @mail(
        $to,
        $subject,
        $message,
        implode("\r\n", $headers)
    );
}


/* =========================
   LIEN WHATSAPP
   ========================= */

function getWhatsAppConfirmationLink($reservation)
{
    $phone =
        normalizeTogoPhone(
            $reservation["telephone"]
        );

    $message =
        getConfirmationMessage($reservation);

    return
        "https://wa.me/" .
        $phone .
        "?text=" .
        rawurlencode($message);
}


/* =========================================================
   DECONNEXION ADMIN
   ========================================================= */

if (isset($_GET["logout"])) {

    unset($_SESSION["admin"]);

    redirectTo("index.php");
}


/* =========================================================
   CONNEXION ADMIN
   ========================================================= */

$loginError = "";

if (isset($_POST["admin_login"])) {

    $email =
        trim($_POST["admin_email"] ?? "");

    $password =
        $_POST["admin_password"] ?? "";

    if (
        $email === $ADMIN_EMAIL &&
        $password === $ADMIN_PASSWORD
    ) {

        $_SESSION["admin"] = true;

        redirectTo(
            "?page=admin&section=dashboard"
        );

    } else {

        $loginError =
            "Email ou mot de passe incorrect.";
    }
}


/* =========================================================
   RESERVATION CLIENT
   ========================================================= */

$reservationSuccess = "";
$reservationError = "";

if (isset($_POST["make_reservation"])) {

    $nom =
        trim($_POST["nom"] ?? "");

    $telephone =
        trim($_POST["telephone"] ?? "");

    $email =
        trim($_POST["email"] ?? "");

    $date =
        $_POST["date_reservation"] ?? "";

    $heure =
        $_POST["heure"] ?? "";

    $personnes =
        (int)($_POST["personnes"] ?? 0);

    $service =
        trim($_POST["service"] ?? "Restaurant");

    $message =
        trim($_POST["message"] ?? "");


    if (
        $nom === "" ||
        $telephone === "" ||
        $date === "" ||
        $heure === "" ||
        $personnes < 1
    ) {

        $reservationError =
            "Veuillez remplir tous les champs obligatoires.";

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO reservations
                (
                    nom,
                    telephone,
                    email,
                    date_reservation,
                    heure,
                    personnes,
                    service,
                    message,
                    statut,
                    confirmation_envoyee
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'En attente', 0)
            ");

            $stmt->execute([
                $nom,
                $telephone,
                $email,
                $date,
                $heure,
                $personnes,
                $service,
                $message
            ]);

            $reservationSuccess =
                "Votre demande de réservation a bien été envoyée. " .
                "Notre équipe vous contactera pour confirmer votre réservation.";

        } catch (PDOException $e) {

            $reservationError =
                "Erreur lors de l'enregistrement de la réservation.";
        }
    }
}


/* =========================================================
   AVIS CLIENT
   ========================================================= */

$avisSuccess = "";

if (isset($_POST["submit_review"])) {

    $nom =
        trim($_POST["avis_nom"] ?? "");

    $email =
        trim($_POST["avis_email"] ?? "");

    $note =
        (int)($_POST["note"] ?? 0);

    $commentaire =
        trim($_POST["commentaire"] ?? "");

    if (
        $nom !== "" &&
        $note >= 1 &&
        $note <= 5 &&
        $commentaire !== ""
    ) {

        $stmt = $pdo->prepare("
            INSERT INTO avis
            (
                nom,
                email,
                note,
                commentaire,
                statut
            )
            VALUES (?, ?, ?, ?, 'En attente')
        ");

        $stmt->execute([
            $nom,
            $email,
            $note,
            $commentaire
        ]);

        $avisSuccess =
            "Merci pour votre avis ! Il sera publié après validation.";
    }
}


/* =========================================================
   CONTACT
   ========================================================= */

$contactSuccess = "";

if (isset($_POST["send_message"])) {

    $nom =
        trim($_POST["contact_name"] ?? "");

    $email =
        trim($_POST["contact_email"] ?? "");

    $telephone =
        trim($_POST["contact_phone"] ?? "");

    $message =
        trim($_POST["contact_message"] ?? "");

    if (
        $nom !== "" &&
        $message !== ""
    ) {

        $stmt = $pdo->prepare("
            INSERT INTO messages
            (
                nom,
                email,
                telephone,
                message
            )
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $nom,
            $email,
            $telephone,
            $message
        ]);

        $contactSuccess =
            "Votre message a bien été envoyé.";
    }
}


/* =========================================================
   ADMIN : STATUT RESERVATION
   ========================================================= */

if (
    isset($_POST["reservation_status"]) &&
    isset($_SESSION["admin"])
) {

    $id =
        (int)($_POST["reservation_id"] ?? 0);

    $status =
        $_POST["reservation_status"] ?? "";

    $allowed = [
        "En attente",
        "Confirmée",
        "Refusée",
        "Terminée"
    ];


    if (
        $id > 0 &&
        in_array($status, $allowed, true)
    ) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM reservations
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $reservation =
            $stmt->fetch();


        if ($reservation) {

            $stmt = $pdo->prepare("
                UPDATE reservations
                SET statut = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $status,
                $id
            ]);


            /* =========================================
               CONFIRMATION
               ========================================= */

            if (
                $status === "Confirmée" &&
                (int)$reservation["confirmation_envoyee"] === 0
            ) {

                $reservation["statut"] =
                    "Confirmée";


                /* EMAIL */

                $emailSent =
                    sendConfirmationEmail(
                        $reservation
                    );


                if ($emailSent) {

                    $stmt = $pdo->prepare("
                        UPDATE reservations
                        SET
                            confirmation_envoyee = 1,
                            notification_date = NOW()
                        WHERE id = ?
                    ");

                    $stmt->execute([$id]);
                }
            }
        }
    }

    redirectTo(
        "?page=admin&section=reservations"
    );
}


/* =========================================================
   ADMIN : AVIS
   ========================================================= */

if (
    isset($_POST["review_action"]) &&
    isset($_SESSION["admin"])
) {

    $id =
        (int)($_POST["review_id"] ?? 0);

    $action =
        $_POST["review_action"];


    if ($action === "approve") {

        $stmt = $pdo->prepare("
            UPDATE avis
            SET statut = 'Publié'
            WHERE id = ?
        ");

        $stmt->execute([$id]);
    }


    if ($action === "reject") {

        $stmt = $pdo->prepare("
            UPDATE avis
            SET statut = 'Refusé'
            WHERE id = ?
        ");

        $stmt->execute([$id]);
    }


    if ($action === "delete") {

        $stmt = $pdo->prepare("
            DELETE FROM avis
            WHERE id = ?
        ");

        $stmt->execute([$id]);
    }


    redirectTo(
        "?page=admin&section=avis"
    );
}


/* =========================================================
   ADMIN : MESSAGES
   ========================================================= */

if (
    isset($_POST["message_action"]) &&
    isset($_SESSION["admin"])
) {

    $id =
        (int)($_POST["message_id"] ?? 0);

    $action =
        $_POST["message_action"];


    if ($action === "read") {

        $stmt = $pdo->prepare("
            UPDATE messages
            SET lu = 1
            WHERE id = ?
        ");

        $stmt->execute([$id]);
    }


    if ($action === "delete") {

        $stmt = $pdo->prepare("
            DELETE FROM messages
            WHERE id = ?
        ");

        $stmt->execute([$id]);
    }


    redirectTo(
        "?page=admin&section=messages"
    );
}


/* =========================================================
   DONNEES PUBLIQUES
   ========================================================= */

$reviews = $pdo->query("
    SELECT *
    FROM avis
    WHERE statut = 'Publié'
    ORDER BY created_at DESC
")->fetchAll();


$averageRating = 0;

if (count($reviews) > 0) {

    $total =
        array_sum(
            array_column(
                $reviews,
                "note"
            )
        );

    $averageRating =
        round(
            $total / count($reviews),
            1
        );
}


/* =========================================================
   ADMIN DONNEES
   ========================================================= */

$reservations = [];
$adminReviews = [];
$messages = [];

if (isset($_SESSION["admin"])) {

    $reservations = $pdo->query("
        SELECT *
        FROM reservations
        ORDER BY created_at DESC
    ")->fetchAll();


    $adminReviews = $pdo->query("
        SELECT *
        FROM avis
        ORDER BY created_at DESC
    ")->fetchAll();


    $messages = $pdo->query("
        SELECT *
        FROM messages
        ORDER BY created_at DESC
    ")->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>
Espace Marina - Restaurant & Events
</title>

<meta
name="description"
content="Espace Marina Lomé - Restaurant, louange bar, piscine, cave à vin et salle de fête."
>


<style>

/* =====================================================
   RESET
   ===================================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


html {
    scroll-behavior: smooth;
}


body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #0c0c0c;

    color: #f5f0e6;

    line-height: 1.6;
}


/* =====================================================
   VARIABLES
   ===================================================== */

:root {

    --black: #0c0c0c;
    --dark: #141414;
    --gold: #d4af37;
    --gold-light: #f1d77b;
    --cream: #f5f0e6;
    --gray: #b9b9b9;
    --white: #ffffff;
    --green: #25d366;
}


/* =====================================================
   HEADER
   ===================================================== */

header {

    position: fixed;

    top: 0;
    left: 0;

    width: 100%;

    z-index: 1000;

    background:
        rgba(10,10,10,.94);

    backdrop-filter:
        blur(12px);

    border-bottom:
        1px solid rgba(212,175,55,.25);
}


.navbar {

    max-width: 1200px;

    margin: auto;

    padding:
        18px 25px;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.logo {

    color: var(--gold);

    font-size: 25px;

    font-weight: 800;

    text-decoration: none;

    letter-spacing: 2px;
}


.logo span {

    color: var(--cream);
}


nav {

    display: flex;

    gap: 25px;
}


nav a {

    color: var(--cream);

    text-decoration: none;

    font-size: 14px;

    transition: .3s;
}


nav a:hover {

    color: var(--gold);
}


/* =====================================================
   HERO
   ===================================================== */

.hero {

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align: center;

    padding: 120px 20px 80px;

    background:

        linear-gradient(
            rgba(0,0,0,.7),
            rgba(0,0,0,.85)
        ),

        url("https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1800&q=90");

    background-size: cover;

    background-position: center;
}


.hero-content {

    max-width: 850px;
}


.hero h1 {

    font-size:
        clamp(45px, 8vw, 90px);

    color: var(--gold);

    line-height: 1;

    margin-bottom: 25px;
}


.hero p {

    font-size: 20px;

    color: #eee;

    margin-bottom: 35px;
}


.buttons {

    display: flex;

    justify-content: center;

    gap: 15px;

    flex-wrap: wrap;
}


.btn {

    display: inline-block;

    padding: 14px 25px;

    border-radius: 30px;

    text-decoration: none;

    font-weight: 700;

    transition: .3s;

    cursor: pointer;

    border: none;
}


.btn-gold {

    background: var(--gold);

    color: #111;
}


.btn-gold:hover {

    background: var(--gold-light);

    transform: translateY(-3px);
}


.btn-outline {

    border: 1px solid var(--gold);

    color: var(--gold);

    background: transparent;
}


.btn-outline:hover {

    background: var(--gold);

    color: #111;
}


/* =====================================================
   SECTIONS
   ===================================================== */

section {

    padding: 100px 20px;
}


.container {

    max-width: 1200px;

    margin: auto;
}


.section-title {

    text-align: center;

    margin-bottom: 55px;
}


.section-title h2 {

    font-size: 42px;

    color: var(--gold);

    margin-bottom: 10px;
}


.section-title p {

    color: var(--gray);
}


/* =====================================================
   ABOUT
   ===================================================== */

.about {

    background: var(--dark);
}


.about-grid {

    display: grid;

    grid-template-columns:
        repeat(2,1fr);

    gap: 50px;

    align-items: center;
}


.about-image img {

    width: 100%;

    border-radius: 20px;

    box-shadow:
        0 20px 60px
        rgba(0,0,0,.5);
}


.about-text h3 {

    color: var(--gold);

    font-size: 30px;

    margin-bottom: 20px;
}


.about-text p {

    color: var(--gray);

    margin-bottom: 15px;
}


/* =====================================================
   SERVICES
   ===================================================== */

.services {

    background: #0a0a0a;
}


.cards {

    display: grid;

    grid-template-columns:
        repeat(5,1fr);

    gap: 18px;
}


.card {

    background: #151515;

    padding: 30px 20px;

    border:
        1px solid
        rgba(212,175,55,.2);

    border-radius: 18px;

    text-align: center;

    transition: .3s;
}


.card:hover {

    transform:
        translateY(-8px);

    border-color:
        var(--gold);
}


.card .icon {

    font-size: 42px;

    margin-bottom: 15px;
}


.card h3 {

    color: var(--gold);

    margin-bottom: 10px;
}


.card p {

    color: var(--gray);

    font-size: 14px;
}


/* =====================================================
   GALLERY
   ===================================================== */

.gallery {

    background: var(--dark);
}


.gallery-grid {

    display: grid;

    grid-template-columns:
        repeat(3,1fr);

    gap: 18px;
}


.gallery-grid img {

    width: 100%;

    height: 260px;

    object-fit: cover;

    border-radius: 15px;

    transition: .4s;
}


.gallery-grid img:hover {

    transform: scale(1.03);
}


/* =====================================================
   GPS
   ===================================================== */

.location {

    text-align: center;

    background:
        linear-gradient(
            135deg,
            #111,
            #19150b
        );
}


.location-box {

    max-width: 800px;

    margin: auto;

    padding: 50px 25px;

    border:
        1px solid
        rgba(212,175,55,.3);

    border-radius: 25px;
}


.location-box h3 {

    color: var(--gold);

    font-size: 30px;

    margin-bottom: 15px;
}


.location-box p {

    color: var(--gray);

    margin-bottom: 25px;
}


#gps-result {

    margin-top: 20px;

    color: var(--gold);
}


/* =====================================================
   AVIS
   ===================================================== */

.reviews {

    background: #090909;
}


.review-grid {

    display: grid;

    grid-template-columns:
        repeat(3,1fr);

    gap: 20px;
}


.review {

    background: #151515;

    padding: 25px;

    border-radius: 18px;
}


.stars {

    color: var(--gold);

    font-size: 20px;

    margin-bottom: 10px;
}


.review p {

    color: #ccc;

    margin-bottom: 15px;
}


.review strong {

    color: var(--cream);
}


/* =====================================================
   FORMS
   ===================================================== */

.form-section {

    background: var(--dark);
}


.form-box {

    max-width: 850px;

    margin: auto;

    background: #111;

    padding: 35px;

    border-radius: 22px;

    border:
        1px solid
        rgba(212,175,55,.25);
}


.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2,1fr);

    gap: 18px;
}


input,
select,
textarea {

    width: 100%;

    padding: 14px;

    border: 1px solid #333;

    background: #1b1b1b;

    color: white;

    border-radius: 10px;

    outline: none;
}


input:focus,
select:focus,
textarea:focus {

    border-color: var(--gold);
}


textarea {

    min-height: 130px;

    resize: vertical;
}


.full {

    grid-column: 1 / -1;
}


.form-box button {

    margin-top: 20px;
}


.success {

    background:
        rgba(37,211,102,.12);

    color:
        #63e68c;

    padding: 15px;

    border-radius: 10px;

    margin-bottom: 20px;
}


.error {

    background:
        rgba(255,50,50,.12);

    color:
        #ff7777;

    padding: 15px;

    border-radius: 10px;

    margin-bottom: 20px;
}


/* =====================================================
   CONTACT
   ===================================================== */

.contact {

    background: #090909;
}


.contact-grid {

    display: grid;

    grid-template-columns:
        repeat(2,1fr);

    gap: 40px;
}


.contact-info {

    padding: 30px;

    background: #151515;

    border-radius: 20px;
}


.contact-info h3 {

    color: var(--gold);

    margin-bottom: 20px;
}


.contact-info p {

    margin-bottom: 15px;

    color: var(--gray);
}


.contact-info a {

    color: var(--gold);

    text-decoration: none;
}


/* =====================================================
   ADMIN
   ===================================================== */

.admin {

    min-height: 100vh;

    padding:
        130px 20px 80px;

    background: #090909;
}


.admin-container {

    max-width: 1400px;

    margin: auto;
}


.admin-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 30px;
}


.admin-header h1 {

    color: var(--gold);
}


.admin-menu {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;

    margin-bottom: 30px;
}


.admin-menu a {

    padding: 10px 16px;

    background: #181818;

    color: #fff;

    text-decoration: none;

    border-radius: 8px;
}


.admin-menu a:hover {

    background: var(--gold);

    color: #111;
}


.table-container {

    overflow-x: auto;

    background: #111;

    border-radius: 15px;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 900px;
}


th,
td {

    padding: 14px;

    border-bottom:
        1px solid #292929;

    text-align: left;
}


th {

    color: var(--gold);

    background: #171717;
}


td {

    color: #ddd;
}


.status {

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 12px;
}


.status-confirmed {

    background: rgba(37,211,102,.15);

    color: #5df28c;
}


.status-pending {

    background: rgba(212,175,55,.15);

    color: var(--gold);
}


.status-refused {

    background: rgba(255,60,60,.15);

    color: #ff7070;
}


.status-finished {

    background: rgba(80,150,255,.15);

    color: #73a9ff;
}


.btn-whatsapp {

    display: inline-block;

    padding: 8px 12px;

    background: var(--green);

    color: white;

    text-decoration: none;

    border-radius: 8px;

    font-size: 12px;

    font-weight: bold;

    margin-top: 6px;
}


.btn-whatsapp:hover {

    opacity: .8;
}


/* =====================================================
   FLOATING WHATSAPP
   ===================================================== */

.whatsapp {

    position: fixed;

    right: 20px;

    bottom: 20px;

    width: 60px;

    height: 60px;

    border-radius: 50%;

    background: var(--green);

    display: flex;

    align-items: center;

    justify-content: center;

    color: white;

    text-decoration: none;

    font-size: 30px;

    z-index: 999;

    box-shadow:
        0 10px 30px
        rgba(0,0,0,.4);
}


/* =====================================================
   FOOTER
   ===================================================== */

footer {

    padding: 50px 20px;

    text-align: center;

    background: #050505;

    border-top:
        1px solid
        rgba(212,175,55,.2);
}


footer h3 {

    color: var(--gold);

    margin-bottom: 10px;
}


footer p {

    color: var(--gray);

    font-size: 14px;
}


/* =====================================================
   RESPONSIVE
   ===================================================== */

@media(max-width: 1000px) {

    .cards {

        grid-template-columns:
            repeat(2,1fr);
    }

    .about-grid,
    .contact-grid {

        grid-template-columns: 1fr;
    }
}


@media(max-width: 750px) {

    nav {

        display: none;
    }

    .form-grid {

        grid-template-columns: 1fr;
    }

    .full {

        grid-column: auto;
    }

    .gallery-grid {

        grid-template-columns: 1fr;
    }

    .review-grid {

        grid-template-columns: 1fr;
    }

    .cards {

        grid-template-columns: 1fr;
    }

    .section-title h2 {

        font-size: 32px;
    }

    .form-box {

        padding: 20px;
    }
}

</style>

</head>


<body>


<?php if (
    !isset($_SESSION["admin"]) ||
    !isset($_GET["page"]) ||
    $_GET["page"] !== "admin"
): ?>


<!-- =====================================================
     NAVIGATION
     ===================================================== -->

<header>

<div class="navbar">

<a
href="index.php"
class="logo"
>
ESPACE <span>MARINA</span>
</a>


<nav>

<a href="#accueil">Accueil</a>

<a href="#maison">La Maison</a>

<a href="#services">Services</a>

<a href="#galerie">Galerie</a>

<a href="#avis">Avis</a>

<a href="#reservation">Réservation</a>

<a href="#contact">Contact</a>

</nav>

</div>

</header>


<!-- =====================================================
     HERO
     ===================================================== -->

<section
class="hero"
id="accueil"
>

<div class="hero-content">

<h1>
Espace Marina
</h1>

<p>
Restaurant • Louange Bar • Piscine • Cave à Vin • Salle de Fête
</p>

<p>
Une expérience élégante et chaleureuse au cœur de Lomé.
</p>


<div class="buttons">

<a
href="#reservation"
class="btn btn-gold"
>
Réserver une table
</a>


<a
href="tel:+22871102929"
class="btn btn-outline"
>
📞 Appeler
</a>

</div>

</div>

</section>


<!-- =====================================================
     LA MAISON
     ===================================================== -->

<section
class="about"
id="maison"
>

<div class="container">

<div class="section-title">

<h2>
La Maison
</h2>

<p>
Découvrez l'univers Espace Marina
</p>

</div>


<div class="about-grid">

<div class="about-image">

<img
src="https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=1000&q=85"
alt="Restaurant"
>

</div>


<div class="about-text">

<h3>
Une destination unique
</h3>

<p>
Espace Marina vous accueille dans un cadre
élégant et convivial à Agoè Légbassito.
</p>

<p>
Notre établissement combine gastronomie,
détente, moments festifs et convivialité.
</p>

<p>
Que vous souhaitiez dîner, vous détendre autour
d'un verre, profiter de la piscine ou organiser
un événement, Espace Marina vous ouvre ses portes.
</p>

</div>

</div>

</div>

</section>


<!-- =====================================================
     SERVICES
     ===================================================== -->

<section
class="services"
id="services"
>

<div class="container">

<div class="section-title">

<h2>
Nos espaces
</h2>

<p>
Tout ce qu'il vous faut au même endroit
</p>

</div>


<div class="cards">

<div class="card">

<div class="icon">
🍽️
</div>

<h3>
Restaurant
</h3>

<p>
Une expérience gastronomique dans un cadre élégant.
</p>

</div>


<div class="card">

<div class="icon">
🍹
</div>

<h3>
Louange Bar
</h3>

<p>
Un espace convivial pour vos soirées et moments de détente.
</p>

</div>


<div class="card">

<div class="icon">
🏊
</div>

<h3>
Piscine
</h3>

<p>
Profitez d'un espace de détente et de fraîcheur.
</p>

</div>


<div class="card">

<div class="icon">
🍷
</div>

<h3>
Cave à vin
</h3>

<p>
Découvrez notre univers autour du vin.
</p>

</div>


<div class="card">

<div class="icon">
🎉
</div>

<h3>
Salle de fête
</h3>

<p>
Organisez vos mariages, anniversaires et événements.
</p>

</div>

</div>

</div>

</section>


<!-- =====================================================
     GALERIE
     ===================================================== -->

<section
class="gallery"
id="galerie"
>

<div class="container">

<div class="section-title">

<h2>
Galerie
</h2>

<p>
Quelques inspirations de notre univers
</p>

</div>


<div class="gallery-grid">

<img
src="https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=1000&q=85"
alt="Restaurant"
>


<img
src="https://images.unsplash.com/photo-1514933651103-005eec06c04b?auto=format&fit=crop&w=1000&q=85"
alt="Bar"
>


<img
src="https://images.unsplash.com/photo-1540541338287-41700207dee6?auto=format&fit=crop&w=1000&q=85"
alt="Piscine"
>


<img
src="https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?auto=format&fit=crop&w=1000&q=85"
alt="Vin"
>


<img
src="https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=1000&q=85"
alt="Événement"
>


<img
src="https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1000&q=85"
alt="Salle"
>

</div>

</div>

</section>


<!-- =====================================================
     GPS
     ===================================================== -->

<section
class="location"
>

<div class="container">

<div class="location-box">

<h3>
📍 Nous trouver
</h3>

<p>
<?= htmlspecialchars($address) ?>
</p>


<button
class="btn btn-gold"
onclick="locateMe()"
>
📍 Me localiser
</button>


<div id="gps-result"></div>

</div>

</div>

</section>


<!-- =====================================================
     AVIS
     ===================================================== -->

<section
class="reviews"
id="avis"
>

<div class="container">

<div class="section-title">

<h2>
Avis de nos clients
</h2>

<p>
Note moyenne :
<strong>
<?= $averageRating ?>
/ 5
</strong>
</p>

</div>


<?php if (count($reviews) > 0): ?>

<div class="review-grid">

<?php foreach ($reviews as $review): ?>

<div class="review">

<div class="stars">

<?= str_repeat(
    "★",
    (int)$review["note"]
) ?>

<?= str_repeat(
    "☆",
    5 - (int)$review["note"]
) ?>

</div>


<p>
<?= nl2br(
    htmlspecialchars(
        $review["commentaire"]
    )
) ?>
</p>


<strong>
—
<?= htmlspecialchars(
    $review["nom"]
) ?>
</strong>

</div>

<?php endforeach; ?>

</div>

<?php else: ?>

<p
style="
text-align:center;
color:#aaa;
"
>
Aucun avis publié pour le moment.
Soyez le premier à partager votre expérience.
</p>

<?php endif; ?>

</div>

</section>


<!-- =====================================================
     FORMULAIRE AVIS
     ===================================================== -->

<section class="form-section">

<div class="container">

<div class="section-title">

<h2>
Donnez votre avis
</h2>

</div>


<div class="form-box">

<?php if ($avisSuccess): ?>

<div class="success">

<?= htmlspecialchars($avisSuccess) ?>

</div>

<?php endif; ?>


<form method="POST">

<div class="form-grid">

<div>

<input
type="text"
name="avis_nom"
placeholder="Votre nom"
required
>

</div>


<div>

<input
type="email"
name="avis_email"
placeholder="Votre e-mail"
>

</div>


<div>

<select
name="note"
required
>

<option value="">
Votre note
</option>

<option value="5">
★★★★★ - Excellent
</option>

<option value="4">
★★★★☆ - Très bien
</option>

<option value="3">
★★★☆☆ - Bien
</option>

<option value="2">
★★☆☆☆ - Moyen
</option>

<option value="1">
★☆☆☆☆ - Mauvais
</option>

</select>

</div>


<div class="full">

<textarea
name="commentaire"
placeholder="Votre commentaire..."
required
></textarea>

</div>

</div>


<button
class="btn btn-gold"
name="submit_review"
type="submit"
>
Publier mon avis
</button>

</form>

</div>

</div>

</section>


<!-- =====================================================
     RESERVATION
     ===================================================== -->

<section
class="form-section"
id="reservation"
>

<div class="container">

<div class="section-title">

<h2>
Réserver
</h2>

<p>
Faites votre demande de réservation
</p>

</div>


<div class="form-box">

<?php if ($reservationSuccess): ?>

<div class="success">

<?= htmlspecialchars(
    $reservationSuccess
) ?>

</div>

<?php endif; ?>


<?php if ($reservationError): ?>

<div class="error">

<?= htmlspecialchars(
    $reservationError
) ?>

</div>

<?php endif; ?>


<form method="POST">

<div class="form-grid">


<div>

<input
type="text"
name="nom"
placeholder="Nom complet"
required
>

</div>


<div>

<input
type="tel"
name="telephone"
placeholder="Téléphone"
required
>

</div>


<div>

<input
type="email"
name="email"
placeholder="E-mail"
>

</div>


<div>

<input
type="number"
name="personnes"
min="1"
max="100"
placeholder="Nombre de personnes"
required
>

</div>


<div>

<label>
Date
</label>

<input
type="date"
name="date_reservation"
required
>

</div>


<div>

<label>
Heure
</label>

<input
type="time"
name="heure"
required
>

</div>


<div class="full">

<select
name="service"
required
>

<option value="Restaurant">
Restaurant
</option>

<option value="Louange Bar">
Louange Bar
</option>

<option value="Piscine">
Piscine
</option>

<option value="Cave à vin">
Cave à vin
</option>

<option value="Salle de fête">
Salle de fête
</option>

</select>

</div>


<div class="full">

<textarea
name="message"
placeholder="Message ou demande particulière..."
></textarea>

</div>

</div>


<button
type="submit"
name="make_reservation"
class="btn btn-gold"
>
Envoyer ma réservation
</button>

</form>

</div>

</div>

</section>


<!-- =====================================================
     CONTACT
     ===================================================== -->

<section
class="contact"
id="contact"
>

<div class="container">

<div class="section-title">

<h2>
Contact
</h2>

</div>


<div class="contact-grid">


<div class="contact-info">

<h3>
Espace Marina
</h3>

<p>
📍 <?= htmlspecialchars($address) ?>
</p>

<p>
📞
<a href="tel:+22871102929">
<?= $phone1 ?>
</a>
</p>

<p>
📞
<a href="tel:+22871101919">
<?= $phone2 ?>
</a>
</p>


<p>

💬

<a
href="https://wa.me/<?= $whatsapp ?>"
target="_blank"
>
WhatsApp
</a>

</p>


<a
href="https://www.google.com/maps/search/?api=1&query=Espace+Marina+Agoe+Legbassito+Lome+Togo"
target="_blank"
class="btn btn-gold"
>
Google Maps
</a>

</div>


<div class="form-box">

<?php if ($contactSuccess): ?>

<div class="success">

<?= htmlspecialchars(
    $contactSuccess
) ?>

</div>

<?php endif; ?>


<form method="POST">

<div class="form-grid">

<div>

<input
type="text"
name="contact_name"
placeholder="Votre nom"
required
>

</div>


<div>

<input
type="email"
name="contact_email"
placeholder="Votre e-mail"
>

</div>


<div class="full">

<input
type="tel"
name="contact_phone"
placeholder="Téléphone"
>

</div>


<div class="full">

<textarea
name="contact_message"
placeholder="Votre message..."
required
></textarea>

</div>

</div>


<button
type="submit"
name="send_message"
class="btn btn-gold"
>
Envoyer
</button>

</form>

</div>

</div>

</div>

</section>


<!-- =====================================================
     FOOTER
     ===================================================== -->

<footer>

<h3>
ESPACE MARINA
</h3>

<p>
Restaurant • Louange Bar • Piscine • Cave à Vin • Salle de Fête
</p>

<p>
© <?= date("Y") ?> Espace Marina — Tous droits réservés.
</p>

</footer>


<!-- WHATSAPP -->

<a
class="whatsapp"
href="https://wa.me/<?= $whatsapp ?>"
target="_blank"
title="Contacter Espace Marina sur WhatsApp"
>
💬
</a>


<script>

/* =====================================================
   GPS
   ===================================================== */

function locateMe()
{
    const result =
        document.getElementById("gps-result");

    if (!navigator.geolocation)
    {
        result.innerHTML =
            "La géolocalisation n'est pas disponible sur votre appareil.";

        return;
    }


    result.innerHTML =
        "📍 Recherche de votre position...";


    navigator.geolocation.getCurrentPosition(

        function(position)
        {
            const latitude =
                position.coords.latitude;

            const longitude =
                position.coords.longitude;


            const destination =
                "Espace+Marina+Agoe+Legbassito+Lome+Togo";


            const url =
                "https://www.google.com/maps/dir/?api=1" +
                "&origin=" +
                latitude +
                "," +
                longitude +
                "&destination=" +
                destination;


            result.innerHTML =
                `
                <a
                    href="${url}"
                    target="_blank"
                    class="btn btn-gold"
                >
                    🚗 Voir l'itinéraire Google Maps
                </a>
                `;
        },

        function(error)
        {
            result.innerHTML =
                "❌ Impossible d'obtenir votre position. " +
                "Veuillez autoriser la localisation.";
        },

        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}


/* =====================================================
   DATE MINIMUM = AUJOURD'HUI
   ===================================================== */

const dateInput =
    document.querySelector(
        'input[name="date_reservation"]'
    );


if (dateInput)
{
    const today =
        new Date()
        .toISOString()
        .split("T")[0];

    dateInput.min = today;
}

</script>


<?php else: ?>


<!-- =====================================================
     ADMIN LOGIN / DASHBOARD
     ===================================================== -->

<section class="admin">

<div class="admin-container">


<?php if (!isset($_SESSION["admin"])): ?>


<div
class="form-box"
style="
max-width:500px;
margin:100px auto;
"
>

<div class="section-title">

<h2>
Administration
</h2>

<p>
Espace Marina
</p>

</div>


<?php if ($loginError): ?>

<div class="error">

<?= htmlspecialchars(
    $loginError
) ?>

</div>

<?php endif; ?>


<form method="POST">

<input
type="email"
name="admin_email"
placeholder="Adresse e-mail"
required
>


<br><br>


<input
type="password"
name="admin_password"
placeholder="Mot de passe"
required
>


<button
type="submit"
name="admin_login"
class="btn btn-gold"
>
Se connecter
</button>

</form>

</div>


<?php else: ?>


<?php

$section =
    $_GET["section"] ?? "dashboard";

?>


<div class="admin-header">

<h1>
Dashboard Espace Marina
</h1>


<a
href="?logout=1"
class="btn btn-outline"
>
Déconnexion
</a>

</div>


<div class="admin-menu">

<a
href="?page=admin&section=dashboard"
>
Dashboard
</a>


<a
href="?page=admin&section=reservations"
>
Réservations
</a>


<a
href="?page=admin&section=avis"
>
Avis
</a>


<a
href="?page=admin&section=messages"
>
Messages
</a>


<a
href="index.php"
target="_blank"
>
Voir le site
</a>

</div>


<?php if ($section === "dashboard"): ?>


<div class="cards">

<div class="card">

<div class="icon">
📅
</div>

<h3>
Réservations
</h3>

<p>
<?= count($reservations) ?>
</p>

</div>


<div class="card">

<div class="icon">
⭐
</div>

<h3>
Avis
</h3>

<p>
<?= count($adminReviews) ?>
</p>

</div>


<div class="card">

<div class="icon">
✉️
</div>

<h3>
Messages
</h3>

<p>
<?= count($messages) ?>
</p>

</div>

</div>


<?php endif; ?>


<?php if ($section === "reservations"): ?>


<h2
style="
color:#d4af37;
margin:30px 0 20px;
"
>
Gestion des réservations
</h2>


<div class="table-container">

<table>

<thead>

<tr>

<th>
Nom
</th>

<th>
Téléphone
</th>

<th>
Date
</th>

<th>
Heure
</th>

<th>
Personnes
</th>

<th>
Service
</th>

<th>
Statut
</th>

<th>
Action
</th>

</tr>

</thead>


<tbody>

<?php foreach ($reservations as $r): ?>

<tr>

<td>
<?= htmlspecialchars($r["nom"]) ?>
</td>


<td>
<?= htmlspecialchars($r["telephone"]) ?>
</td>


<td>
<?= date(
    "d/m/Y",
    strtotime($r["date_reservation"])
) ?>
</td>


<td>
<?= date(
    "H:i",
    strtotime($r["heure"])
) ?>
</td>


<td>
<?= (int)$r["personnes"] ?>
</td>


<td>
<?= htmlspecialchars($r["service"]) ?>
</td>


<td>

<?php

$statusClass =
    "status-pending";

if ($r["statut"] === "Confirmée") {
    $statusClass =
        "status-confirmed";
}

if ($r["statut"] === "Refusée") {
    $statusClass =
        "status-refused";
}

if ($r["statut"] === "Terminée") {
    $statusClass =
        "status-finished";
}

?>

<span
class="status <?= $statusClass ?>"
>
<?= htmlspecialchars(
    $r["statut"]
) ?>
</span>

</td>


<td>

<form
method="POST"
style="
display:inline-block;
"
>

<input
type="hidden"
name="reservation_id"
value="<?= $r["id"] ?>"
>


<select
name="reservation_status"
onchange="this.form.submit()"
>

<option
value="En attente"
<?= $r["statut"] === "En attente"
    ? "selected"
    : "" ?>
>
En attente
</option>


<option
value="Confirmée"
<?= $r["statut"] === "Confirmée"
    ? "selected"
    : "" ?>
>
Confirmée
</option>


<option
value="Refusée"
<?= $r["statut"] === "Refusée"
    ? "selected"
    : "" ?>
>
Refusée
</option>


<option
value="Terminée"
<?= $r["statut"] === "Terminée"
    ? "selected"
    : "" ?>
>
Terminée
</option>

</select>

</form>


<?php if ($r["statut"] === "Confirmée"): ?>

<br>


<a
href="<?= htmlspecialchars(
    getWhatsAppConfirmationLink($r)
) ?>"
target="_blank"
class="btn-whatsapp"
>
💬 WhatsApp
</a>

<?php endif; ?>


</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<?php endif; ?>


<?php if ($section === "avis"): ?>


<h2
style="
color:#d4af37;
margin:30px 0 20px;
"
>
Gestion des avis
</h2>


<div class="table-container">

<table>

<thead>

<tr>

<th>
Nom
</th>

<th>
Note
</th>

<th>
Commentaire
</th>

<th>
Statut
</th>

<th>
Action
</th>

</tr>

</thead>


<tbody>

<?php foreach ($adminReviews as $r): ?>

<tr>

<td>
<?= htmlspecialchars(
    $r["nom"]
) ?>
</td>


<td>

<?= str_repeat(
    "★",
    (int)$r["note"]
) ?>

</td>


<td>

<?= htmlspecialchars(
    $r["commentaire"]
) ?>

</td>


<td>

<?= htmlspecialchars(
    $r["statut"]
) ?>

</td>


<td>


<form
method="POST"
style="display:inline"
>

<input
type="hidden"
name="review_id"
value="<?= $r["id"] ?>"
>


<button
class="btn btn-gold"
name="review_action"
value="approve"
>
Publier
</button>


<button
class="btn btn-outline"
name="review_action"
value="reject"
>
Refuser
</button>


<button
class="btn btn-outline"
name="review_action"
value="delete"
>
Supprimer
</button>

</form>


</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<?php endif; ?>


<?php if ($section === "messages"): ?>


<h2
style="
color:#d4af37;
margin:30px 0 20px;
"
>
Messages clients
</h2>


<div class="table-container">

<table>

<thead>

<tr>

<th>
Nom
</th>

<th>
Email
</th>

<th>
Téléphone
</th>

<th>
Message
</th>

<th>
Lu
</th>

<th>
Action
</th>

</tr>

</thead>


<tbody>

<?php foreach ($messages as $m): ?>

<tr>

<td>
<?= htmlspecialchars(
    $m["nom"]
) ?>
</td>


<td>
<?= htmlspecialchars(
    $m["email"]
) ?>
</td>


<td>
<?= htmlspecialchars(
    $m["telephone"]
) ?>
</td>


<td>
<?= nl2br(
    htmlspecialchars(
        $m["message"]
    )
) ?>
</td>


<td>

<?= $m["lu"]
    ? "Oui"
    : "Non"
?>

</td>


<td>


<form
method="POST"
>

<input
type="hidden"
name="message_id"
value="<?= $m["id"] ?>"
>


<button
class="btn btn-gold"
name="message_action"
value="read"
>
Marquer lu
</button>


<button
class="btn btn-outline"
name="message_action"
value="delete"
>
Supprimer
</button>

</form>


</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<?php endif; ?>


<?php endif; ?>

</div>

</section>


<?php endif; ?>


</body>

</html>