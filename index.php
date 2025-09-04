<?php
// Includiamo la libreria FPDF per generare PDF
require('fpdf/fpdf.php');

// Includiamo PHPMailer per l'invio delle email
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

// Impostiamo l'header per la codifica UTF-8
header('Content-Type: text/html; charset=utf-8');

// Carica la configurazione SMTP
$config_file = 'smtp_config.php';
if (file_exists($config_file)) {
    include $config_file;
} else {
    // Configurazione predefinita (vuota)
    $smtp_config = [
        'host' => '',
        'username' => '',
        'password' => '',
        'port' => 587,
        'encryption' => 'tls',
        'from_email' => '',
        'from_name' => 'Sistema Pianificazione'
    ];
    $email_destinatario = '';
    $email_cc = '';
}

// Gestione della data di riferimento
$data_riferimento = isset($_POST['data_riferimento']) ? new DateTime($_POST['data_riferimento']) : new DateTime();

// Se è stato richiesto di cambiare la settimana di riferimento
if (isset($_POST['cambia_settimana'])) {
    // Ottieni l'anno e il numero della settimana dalla selezione
    list($anno, $settimana) = explode('-W', $_POST['nuova_settimana']);
    $data_riferimento = new DateTime();
    $data_riferimento->setISODate($anno, $settimana);
}

// Troviamo il lunedì della settimana di riferimento
$lunedi = clone $data_riferimento;
$lunedi->modify('monday this week');

// Calcoliamo le date per tutta la settimana
$giorni_settimana = [
    'Lunedì' => clone $lunedi,
    'Martedì' => clone $lunedi->modify('+1 day'),
    'Mercoledì' => clone $lunedi->modify('+1 day'),
    'Giovedì' => clone $lunedi->modify('+1 day'),
    'Venerdì' => clone $lunedi->modify('+1 day'),
    'Sabato' => clone $lunedi->modify('+1 day'),
    'Domenica' => clone $lunedi->modify('+1 day')
];

// Reset di lunedi per il periodo
$lunedi_periodo = clone $lunedi;
$lunedi_periodo->modify('-6 days');
$periodo_testo = $lunedi_periodo->format('d/m/Y') . ' - ' . $lunedi->format('d/m/Y');

// Ottieni l'anno e il numero della settimana corrente per il menu a tendina
$anno_corrente = $data_riferimento->format('Y');
$settimana_corrente = $data_riferimento->format('W');
$settimana_selezionata = $anno_corrente . '-W' . ($settimana_corrente < 10 ? '0' . $settimana_corrente : $settimana_corrente);

// Genera opzioni per le settimane (dalla settimana corrente -4 fino a +4 settimane)
$opzioni_settimane = [];
for ($i = -4; $i <= 4; $i++) {
    $data_settimana = new DateTime();
    $data_settimana->modify('+' . $i . ' weeks');
    $anno = $data_settimana->format('Y');
    $settimana = $data_settimana->format('W');
    $opzioni_settimane[] = $anno . '-W' . ($settimana < 10 ? '0' . $settimana : $settimana);
}

// Gestione dell'invio email
if (isset($_POST['invia_email'])) {
    // Verifica se la configurazione SMTP è completa
    if (empty($smtp_config['host']) || empty($smtp_config['username']) || empty($smtp_config['password'])) {
        $messaggio_errore = "Configurazione SMTP non completata. Contatta l'amministratore.";
    } else {
        // Genera il PDF in memoria
        $pdf_data = generaPDF($giorni_settimana, $periodo_testo);
        
        // Invia l'email con il PDF allegato
        $risultato_invio = inviaEmail($pdf_data, $periodo_testo, $smtp_config, $email_destinatario, $email_cc);
        
        if ($risultato_invio === true) {
            $messaggio_successo = "Email inviata con successo a $email_destinatario";
            if (!empty($email_cc)) {
                $messaggio_successo .= " e in CC a $email_cc";
            }
        } else {
            $messaggio_errore = "Errore nell'invio dell'email: " . $risultato_invio;
        }
    }
}

// Gestione dell'esportazione PDF
if (isset($_POST['genera_pdf'])) {
    $pdf_data = generaPDF($giorni_settimana, $periodo_testo);
    
    // Forza il download del PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="pianificazione_settimanale.pdf"');
    header('Content-Length: ' . strlen($pdf_data));
    echo $pdf_data;
    exit;
}

// Funzione per generare il PDF
function generaPDF($giorni_settimana, $periodo_testo) {
    class PDF extends FPDF {
        private $periodo;
        
        function __construct($periodo) {
            parent::__construct('L');
            $this->periodo = $periodo;
        }
        
        function Header() {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, 'Pianificazione Settimanale', 0, 1, 'C');
            $this->SetFont('Arial', 'I', 12);
            $this->Cell(0, 10, 'Periodo: ' . $this->periodo, 0, 1, 'C');
            $this->Ln(5);
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo(), 0, 0, 'C');
        }
        
        function CreateTable($header, $data) {
            // Colori, larghezza linea e grassetto
            $this->SetFillColor(100, 100, 150);
            $this->SetTextColor(255);
            $this->SetDrawColor(0, 0, 0);
            $this->SetLineWidth(.3);
            $this->SetFont('', 'B');
            
            // Intestazioni colonne
            $w = array(40, 35, 35, 35, 35, 35, 35, 35);
            for($i=0; $i<count($header); $i++) {
                $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
            }
            $this->Ln();
            
            // Ripristino colore e font
            $this->SetFillColor(224, 235, 255);
            $this->SetTextColor(0);
            $this->SetFont('');
            
            // Dati
            $fill = false;
            foreach($data as $row) {
                $this->Cell($w[0], 6, $row[0], 'LR', 0, 'L', $fill);
                for($i=1; $i<count($row); $i++) {
                    $check = $row[$i] ? 'X' : '';
                    $this->SetFont('', 'B');
                    $this->Cell($w[$i], 6, $check, 'LR', 0, 'C', $fill);
                    $this->SetFont('');
                }
                $this->Ln();
                $fill = !$fill;
            }
            // Chiusura tabella
            $this->Cell(array_sum($w), 0, '', 'T');
        }
    }

    $pdf = new PDF($periodo_testo);
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);
    
    // Intestazioni colonne con a capo - usando caratteri corretti
    $header = array('Quadranti', 
                    "Lunedì\n" . $giorni_settimana['Lunedì']->format('d/m'), 
                    "Martedì\n" . $giorni_settimana['Martedì']->format('d/m'), 
                    "Mercoledì\n" . $giorni_settimana['Mercoledì']->format('d/m'), 
                    "Giovedì\n" . $giorni_settimana['Giovedì']->format('d/m'), 
                    "Venerdì\n" . $giorni_settimana['Venerdì']->format('d/m'), 
                    "Sabato\n" . $giorni_settimana['Sabato']->format('d/m'), 
                    "Domenica\n" . $giorni_settimana['Domenica']->format('d/m'));
    
    // Dati dalla form
    $data = array();
    $quadranti = array('Notte', 'Mattina', 'Pomeriggio', 'Sera');
    
    foreach($quadranti as $quadrante) {
        $row = array($quadrante);
        foreach($giorni_settimana as $giorno => $data_obj) {
            $name = strtolower($giorno) . '_' . strtolower($quadrante);
            $row[] = isset($_POST[$name]) ? 1 : 0;
        }
        $data[] = $row;
    }
    
    $pdf->CreateTable($header, $data);
    
    // Restituisce il PDF como stringa
    return $pdf->Output('S');
}

// Funzione per inviare l'email
function inviaEmail($pdf_data, $periodo_testo, $smtp_config, $destinatario, $cc) {
    $mail = new PHPMailer(true);
    
    try {
        // Configurazione server SMTP
        $mail->isSMTP();
        $mail->Host = $smtp_config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_config['username'];
        $mail->Password = $smtp_config['password'];
        $mail->SMTPSecure = $smtp_config['encryption'];
        $mail->Port = $smtp_config['port'];
        
        // Abilita debug se necessario
        // $mail->SMTPDebug = 2; // Abilita output di debug dettagliato
        
        // Mittente
        $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
        
        // Destinatari
        $mail->addAddress($destinatario);
        if (!empty($cc)) {
            $mail->addCC($cc);
        }
        
        // Contenuto email
        $mail->isHTML(true);
        $mail->Subject = 'Pianificazione Settimanale - ' . $periodo_testo;
        $mail->Body    = 'In allegato la pianificazione settimanale per il periodo ' . $periodo_testo . '.';
        $mail->AltBody = 'In allegato la pianificazione settimanale per il periodo ' . $periodo_testo . '.';
        
        // Allega il PDF
        $mail->addStringAttachment($pdf_data, 'pianificazione_settimanale.pdf');
        
        // Invia l'email
        $mail->send();
        return true;
    } catch (Exception $e) {
        return $mail->ErrorInfo;
    }
}

// Gestione del salvataggio dei dati (opzionale)
$dati_salvati = array();
if (isset($_POST['salva'])) {
    foreach ($_POST as $key => $value) {
        if (strpos($key, '_') !== false) {
            $dati_salvati[$key] = $value;
        }
    }
    // Salva anche la data di riferimento
    $dati_salvati['data_riferimento'] = $data_riferimento->format('Y-m-d');
}

// Gestione del pulsante "Pulisci"
if (isset($_POST['pulisci'])) {
    $dati_salvati = array();
}

// Caricamento dati salvati
if (isset($dati_salvati['data_riferimento'])) {
    $data_riferimento = new DateTime($dati_salvati['data_riferimento']);
    // Ricalcola i giorni della settimana in base alla data salvata
    $lunedi = clone $data_riferimento;
    $lunedi->modify('monday this week');
    
    $giorni_settimana = [
        'Lunedì' => clone $lunedi,
        'Martedì' => clone $lunedi->modify('+1 day'),
        'Mercoledì' => clone $lunedi->modify('+1 day'),
        'Giovedì' => clone $lunedi->modify('+1 day'),
        'Venerdì' => clone $lunedi->modify('+1 day'),
        'Sabato' => clone $lunedi->modify('+1 day'),
        'Domenica' => clone $lunedi->modify('+1 day')
    ];
    
    // Aggiorna la settimana selezionata
    $anno_corrente = $data_riferimento->format('Y');
    $settimana_corrente = $data_riferimento->format('W');
    $settimana_selezionata = $anno_corrente . '-W' . ($settimana_corrente < 10 ? '0' . $settimana_corrente : $settimana_corrente);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pianificazione Settimanale</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 5px;
        }
        .admin-link {
            text-align: right;
            margin-bottom: 10px;
        }
        .admin-link a {
            color: #6c757d;
            text-decoration: none;
        }
        .admin-link a:hover {
            text-decoration: underline;
        }
        .controlli-data {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .controlli-data label {
            margin-right: 10px;
            font-weight: bold;
        }
        .controlli-data select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .controlli-data button {
            padding: 5px 10px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .periodo {
            text-align: center;
            font-style: italic;
            margin-bottom: 20px;
            color: #666;
            font-size: 18px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: white;
            box-shadow: 0 0 5px rgba(0,0,0,0.05);
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }
        th {
            background-color: #4CAF50;
            color: white;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .quadrante {
            font-weight: bold;
            background-color: #e7f3fe;
        }
        input[type="checkbox"] {
            transform: scale(1.5);
            cursor: pointer;
        }
        .pulsanti {
            text-align: center;
            margin: 20px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        button {
            padding: 10px 20px;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
            min-width: 140px;
        }
        button[name="salva"] {
            background-color: #2196F3;
        }
        button[name="salva"]:hover {
            background-color: #0b7dda;
        }
        button[name="genera_pdf"] {
            background-color: #f44336;
        }
        button[name="genera_pdf"]:hover {
            background-color: #d32f2f;
        }
        button[name="invia_email"] {
            background-color: #4CAF50;
        }
        button[name="invia_email"]:hover {
            background-color: #45a049;
        }
        button[name="pulisci"] {
            background-color: #FF9800;
        }
        button[name="pulisci"]:hover {
            background-color: #e68a00;
        }
        .info {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
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
        .warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        @media print {
            .pulsanti, .info, .controlli-data, .admin-link {
                display: none;
            }
            .container {
                box-shadow: none;
                padding: 0;
            }
        }
        @media (max-width: 768px) {
            .pulsanti {
                flex-direction: column;
                align-items: center;
            }
            button {
                width: 100%;
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-link">
            <a href="admin_config.php">Configurazione SMTP</a>
        </div>
        
        <h1>Pianificazione Settimanale</h1>
        
        <?php if (isset($messaggio_successo)): ?>
            <div class="messaggio successo"><?php echo $messaggio_successo; ?></div>
        <?php endif; ?>
        
        <?php if (isset($messaggio_errore)): ?>
            <div class="messaggio errore"><?php echo $messaggio_errore; ?></div>
        <?php endif; ?>
        
        <?php if (empty($smtp_config['host']) || empty($smtp_config['username'])): ?>
            <div class="messaggio warning">
                Configurazione SMTP non completata. <a href="admin_config.php">Clicca qui per configurarla</a>.
            </div>
        <?php endif; ?>
        
        <div class="controlli-data">
            <form method="post">
                <label for="nuova_settimana">Seleziona la settimana di riferimento:</label>
                <select id="nuova_settimana" name="nuova_settimana" required>
                    <?php foreach ($opzioni_settimane as $opzione): 
                        $parti = explode('-W', $opzione);
                        $anno = $parti[0];
                        $settimana = $parti[1];
                        $data_inizio = new DateTime();
                        $data_inizio->setISODate($anno, $settimana);
                        $data_fine = clone $data_inizio;
                        $data_fine->modify('+6 days');
                        $testo_settimana = "Settimana $settimana/$anno (" . $data_inizio->format('d/m') . " - " . $data_fine->format('d/m') . ")";
                    ?>
                        <option value="<?php echo $opzione; ?>" <?php echo ($opzione == $settimana_selezionata) ? 'selected' : ''; ?>>
                            <?php echo $testo_settimana; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="cambia_settimana">Cambia Settimana</button>
            </form>
        </div>
        
        <div class="periodo">Periodo: <?php echo $periodo_testo; ?></div>
        
        <form method="post">
            <input type="hidden" name="data_riferimento" value="<?php echo $data_riferimento->format('Y-m-d'); ?>">
            
            <table>
                <thead>
                    <tr>
                        <th>Quadranti/Giorni</th>
                        <th>Lunedì<br><?php echo $giorni_settimana['Lunedì']->format('d/m'); ?></th>
                        <th>Martedì<br><?php echo $giorni_settimana['Martedì']->format('d/m'); ?></th>
                        <th>Mercoledì<br><?php echo $giorni_settimana['Mercoledì']->format('d/m'); ?></th>
                        <th>Giovedì<br><?php echo $giorni_settimana['Giovedì']->format('d/m'); ?></th>
                        <th>Venerdì<br><?php echo $giorni_settimana['Venerdì']->format('d/m'); ?></th>
                        <th>Sabato<br><?php echo $giorni_settimana['Sabato']->format('d/m'); ?></th>
                        <th>Domenica<br><?php echo $giorni_settimana['Domenica']->format('d/m'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $quadranti = array('Notte', 'Mattina', 'Pomeriggio', 'Sera');
                    
                    foreach ($quadranti as $quadrante) {
                        echo "<tr>";
                        echo "<td class='quadrante'>$quadrante</td>";
                        
                        foreach ($giorni_settimana as $giorno => $data_obj) {
                            $name = strtolower($giorno) . '_' . strtolower($quadrante);
                            $checked = isset($dati_salvati[$name]) ? 'checked' : '';
                            echo "<td><input type='checkbox' name='$name' $checked></td>";
                        }
                        
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
            
            <div class="pulsanti">
                <button type="submit" name="salva">Salva</button>
                <button type="submit" name="genera_pdf">Genera PDF</button>
                <button type="submit" name="invia_email" <?php echo (empty($smtp_config['host']) ? 'disabled' : ''); ?>>Invia Email</button>
                <button type="submit" name="pulisci">Pulisci</button>
            </div>
        </form>
        
        <div class="info">
            <p>Seleziona una settimana per visualizzare la pianificazione. I dati vengono salvati per ogni settimana separatamente.</p>
            <?php if (!empty($email_destinatario)): ?>
                <p>Il PDF verrà inviato a: <strong><?php echo $email_destinatario; ?></strong></p>
            <?php endif; ?>
            <?php if (!empty($email_cc)): ?>
                <p>Con copia a: <strong><?php echo $email_cc; ?></strong></p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Aggiunge funzionalità per il salvataggio nel localStorage
        document.addEventListener('DOMContentLoaded', function() {
            // Carica i dati salvati dal localStorage
            const dataRiferimento = document.querySelector('input[name="data_riferimento"]').value;
            const savedData = localStorage.getItem('pianificazione_settimana_' + dataRiferimento);
            
            if (savedData) {
                const data = JSON.parse(savedData);
                for (const key in data) {
                    const checkbox = document.querySelector(`input[name="${key}"]`);
                    if (checkbox) {
                        checkbox.checked = data[key];
                    }
                }
            }
            
            // Salva i dati quando cambiano le checkbox
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', saveCheckboxes);
            });
            
            // Gestione del pulsante Pulisci
            document.querySelector('button[name="pulisci"]').addEventListener('click', function(e) {
                if (!confirm('Sei sicuro di voler cancellare tutte le selezioni?')) {
                    e.preventDefault();
                    return;
                }
                
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                
                const dataRiferimento = document.querySelector('input[name="data_riferimento"]').value;
                localStorage.removeItem('pianificazione_settimana_' + dataRiferimento);
            });
            
            // Gestione del pulsante Invia Email
            const inviaEmailBtn = document.querySelector('button[name="invia_email"]');
            if (inviaEmailBtn) {
                inviaEmailBtn.addEventListener('click', function(e) {
                    if (!confirm('Sei sicuro di voler inviare la pianificazione via email?')) {
                        e.preventDefault();
                        return;
                    }
                });
            }
        });
        
        function saveCheckboxes() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            const dataRiferimento = document.querySelector('input[name="data_riferimento"]').value;
            const data = {};
            
            checkboxes.forEach(checkbox => {
                data[checkbox.name] = checkbox.checked;
            });
            
            localStorage.setItem('pianificazione_settimana_' + dataRiferimento, JSON.stringify(data));
        }
    </script>
</body>
</html>
