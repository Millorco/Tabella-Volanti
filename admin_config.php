
<?php
// Pagina di configurazione SMTP per l'amministratore

// Password di amministrazione (modifica questa password)
$admin_password = 'admin123';

// Avvia la sessione
session_start();

// Verifica se l'utente è loggato
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Se non è loggato, verifica se sta tentando di effettuare il login
    if (isset($_POST['password'])) {
        if ($_POST['password'] === $admin_password) {
            $_SESSION['admin_logged_in'] = true;
        } else {
            $messaggio_errore = "Password errata. Riprova.";
        }
    }
    
    // Se ancora non è loggato, mostra il form di login
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        ?>
        <!DOCTYPE html>
        <html lang="it">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Accesso Amministratore</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 20px;
                    background-color: #f5f5f5;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                }
                .login-container {
                    background-color: white;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    width: 100%;
                    max-width: 400px;
                }
                h1 {
                    text-align: center;
                    color: #333;
                    margin-bottom: 20px;
                }
                .form-group {
                    margin-bottom: 15px;
                }
                .form-group label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: bold;
                }
                .form-group input {
                    width: 100%;
                    padding: 10px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    box-sizing: border-box;
                }
                .form-actions {
                    text-align: center;
                    margin-top: 20px;
                }
                button {
                    padding: 10px 20px;
                    background-color: #4CAF50;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 16px;
                }
                button:hover {
                    background-color: #45a049;
                }
                .messaggio {
                    padding: 10px;
                    margin: 10px 0;
                    border-radius: 4px;
                    text-align: center;
                }
                .errore {
                    background-color: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
                .back-link {
                    text-align: center;
                    margin-top: 20px;
                }
                .back-link a {
                    color: #6c757d;
                    text-decoration: none;
                }
                .back-link a:hover {
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <h1>Accesso Amministratore</h1>
                
                <?php if (isset($messaggio_errore)): ?>
                    <div class="messaggio errore"><?php echo $messaggio_errore; ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit">Accedi</button>
                    </div>
                </form>
                
                <div class="back-link">
                    <a href="index.php">&larr; Torna alla Pianificazione</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Verifica se è una richiesta POST (salvataggio configurazione)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['smtp_host'])) {
    $config_data = "<?php\n";
    $config_data .= "// Configurazione SMTP - Modificare con i propri dati\n";
    $config_data .= "\$smtp_config = [\n";
    $config_data .= "    'host' => '" . addslashes($_POST['smtp_host']) . "',\n";
    $config_data .= "    'username' => '" . addslashes($_POST['smtp_username']) . "',\n";
    $config_data .= "    'password' => '" . addslashes($_POST['smtp_password']) . "',\n";
    $config_data .= "    'port' => " . intval($_POST['smtp_port']) . ",\n";
    $config_data .= "    'encryption' => '" . addslashes($_POST['smtp_encryption']) . "',\n";
    $config_data .= "    'from_email' => '" . addslashes($_POST['from_email']) . "',\n";
    $config_data .= "    'from_name' => '" . addslashes($_POST['from_name']) . "'\n";
    $config_data .= "];\n\n";
    $config_data .= "// Indirizzi email destinatari\n";
    $config_data .= "\$email_destinatario = '" . addslashes($_POST['email_destinatario']) . "';\n";
    $config_data .= "\$email_cc = '" . addslashes($_POST['email_cc']) . "';\n";
    $config_data .= "?>";
    
    // Salva la configurazione nel file
    if (file_put_contents('smtp_config.php', $config_data)) {
        $messaggio_successo = "Configurazione salvata con successo!";
    } else {
        $messaggio_errore = "Errore nel salvataggio della configurazione. Verifica i permessi del file.";
    }
}

// Leggi la configurazione esistente
$config_file = 'smtp_config.php';
if (file_exists($config_file)) {
    include $config_file;
} else {
    // Valori predefiniti
    $smtp_config = [
        'host' => '',
        'username' => '',
        'password' => '',
        'port' => '587',
        'encryption' => 'tls',
        'from_email' => '',
        'from_name' => 'Sistema Pianificazione'
    ];
    $email_destinatario = '';
    $email_cc = '';
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_config.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurazione SMTP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .admin-actions a {
            color: #6c757d;
            text-decoration: none;
            margin-left: 15px;
        }
        .admin-actions a:hover {
            text-decoration: underline;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-actions {
            text-align: center;
            margin-top: 20px;
        }
        button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        .messaggio {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            text-align: center;
        }
        .successo {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .errore {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <a href="index.php">&larr; Torna alla Pianificazione</a>
            <div class="admin-actions">
                <a href="?logout=true">Logout</a>
            </div>
        </div>
        
        <h1>Configurazione SMTP</h1>
        
        <?php if (isset($messaggio_successo)): ?>
            <div class="messaggio successo"><?php echo $messaggio_successo; ?></div>
        <?php endif; ?>
        
        <?php if (isset($messaggio_errore)): ?>
            <div class="messaggio errore"><?php echo $messaggio_errore; ?></div>
        <?php endif; ?>
        
        <div class="info">
            <p><strong>Informazioni per la configurazione:</strong></p>
            <p>Inserisci i dati del server SMTP forniti dal tuo provider email. Di solito puoi trovare queste informazioni nel pannello di controllo del tuo account email o contattando l'assistenza.</p>
            <p>Esempi comuni:</p>
            <ul>
                <li>Gmail: smtp.gmail.com, porta 587, encryption TLS</li>
                <li>Outlook: smtp-mail.outlook.com, porta 587, encryption TLS</li>
                <li>Yahoo: smtp.mail.yahoo.com, porta 465, encryption SSL</li>
            </ul>
        </div>
        
        <form method="post">
            <div class="form-group">
                <label for="smtp_host">SMTP Host:</label>
                <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($smtp_config['host']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="smtp_username">SMTP Username:</label>
                <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($smtp_config['username']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="smtp_password">SMTP Password:</label>
                <input type="password" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($smtp_config['password']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="smtp_port">SMTP Port:</label>
                <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($smtp_config['port']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="smtp_encryption">Encryption:</label>
                <select id="smtp_encryption" name="smtp_encryption" required>
                    <option value="tls" <?php echo ($smtp_config['encryption'] == 'tls') ? 'selected' : ''; ?>>TLS</option>
                    <option value="ssl" <?php echo ($smtp_config['encryption'] == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="from_email">Email Mittente:</label>
                <input type="email" id="from_email" name="from_email" value="<?php echo htmlspecialchars($smtp_config['from_email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="from_name">Nome Mittente:</label>
                <input type="text" id="from_name" name="from_name" value="<?php echo htmlspecialchars($smtp_config['from_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email_destinatario">Email Destinatario:</label>
                <input type="email" id="email_destinatario" name="email_destinatario" value="<?php echo htmlspecialchars($email_destinatario); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email_cc">Email CC (opzionale):</label>
                <input type="email" id="email_cc" name="email_cc" value="<?php echo htmlspecialchars($email_cc); ?>">
            </div>
            
            <div class="form-actions">
                <button type="submit">Salva Configurazione</button>
            </div>
        </form>
    </div>
</body>
</html>
