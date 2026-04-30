#!/usr/bin/env php
<?php
/**
 * SMS Vert Pro — Serveur MCP (Model Context Protocol)
 *
 * Permet aux agents IA (Claude, GPT, LangChain, CrewAI, etc.)
 * d'envoyer des SMS via l'API SMS Vert Pro V2.
 *
 * Prérequis :
 *   1. Créez un compte gratuit sur https://www.smsvertpro.com
 *   2. Générez votre token API Bearer depuis l'API V2
 *   3. Définissez la variable d'environnement SMSVERTPRO_API_TOKEN
 *
 * Usage :
 *   SMSVERTPRO_API_TOKEN=votre_token php server.php
 *
 * @link https://www.smsvertpro.com/api-smsvertpro/
 * @link https://www.smsvertpro.com/integration-ia/
 */

// ─── Configuration ───────────────────────────────────────────────

define('API_URL',     'https://www.smsvertpro.com/api/v2/');
define('SERVER_NAME', 'smsvertpro');
define('SERVER_VERSION', '1.0.0');

$apiToken = getenv('SMSVERTPRO_API_TOKEN');
if(empty($apiToken))
{
    fwrite(STDERR, "[ERREUR] Variable d'environnement SMSVERTPRO_API_TOKEN non définie.\n");
    fwrite(STDERR, "Créez un compte sur https://www.smsvertpro.com puis générez votre token API.\n");
    exit(1);
}

// ─── Tools Definition ────────────────────────────────────────────

$tools = array(
    array(
        'name' => 'send_sms',
        'description' => "Envoie un SMS à un ou plusieurs destinataires via SMS Vert Pro. Les numéros doivent être au format international sans le '+' (ex: 33612345678 pour un numéro français). Le message est limité à 160 caractères pour 1 SMS (ou 306 pour 2 SMS concaténés). IMPORTANT : si le compte est configuré en route marketing, l'expéditeur doit ajouter la mention 'STOP 36173' à la fin du message (obligation légale pour les SMS commerciaux). Cette mention n'est PAS ajoutée automatiquement par l'API.",
        'inputSchema' => array(
            'type' => 'object',
            'properties' => array(
                'to' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                    'description' => "Liste des numéros destinataires au format international (ex: ['33612345678'])"
                ),
                'message' => array(
                    'type' => 'string',
                    'description' => 'Le contenu du SMS à envoyer'
                ),
                'sender' => array(
                    'type' => 'string',
                    'description' => "Nom de l'expéditeur affiché (11 car. max, alphanumérique). Ex: 'MaSociete'"
                ),
                'delay' => array(
                    'type' => 'string',
                    'description' => "Envoi différé (optionnel). Format: 'YYYY-MM-DD HH:MM'. Ex: '2026-04-15 09:00'"
                )
            ),
            'required' => array('to', 'message', 'sender')
        )
    ),
    array(
        'name' => 'check_credits',
        'description' => "Consulte le solde de crédits SMS restants sur le compte SMS Vert Pro. 1 crédit = 1 SMS de 160 caractères.",
        'inputSchema' => array(
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => array()
        )
    ),
    array(
        'name' => 'get_delivery_report',
        'description' => "Récupère le rapport de délivrabilité d'une campagne SMS envoyée. Permet de vérifier si les SMS ont été délivrés, sont en attente ou en erreur.",
        'inputSchema' => array(
            'type' => 'object',
            'properties' => array(
                'campaign_id' => array(
                    'type' => 'string',
                    'description' => "L'identifiant de la campagne retourné lors de l'envoi"
                )
            ),
            'required' => array('campaign_id')
        )
    ),
    array(
        'name' => 'get_responses',
        'description' => "Récupère les réponses SMS reçues pour une campagne donnée (SMS bidirectionnel). Utile pour les enquêtes, confirmations ou échanges avec les destinataires.",
        'inputSchema' => array(
            'type' => 'object',
            'properties' => array(
                'campaign_id' => array(
                    'type' => 'string',
                    'description' => "L'identifiant de la campagne"
                )
            ),
            'required' => array('campaign_id')
        )
    ),
    array(
        'name' => 'verify_number',
        'description' => "Vérifie le format des numéros de téléphone d'une liste de contacts (syntaxe, longueur, indicatif). Ne vérifie pas si le numéro est actif, uniquement si le format est correct.",
        'inputSchema' => array(
            'type' => 'object',
            'properties' => array(
                'list_id' => array(
                    'type' => 'string',
                    'description' => "L'identifiant de la liste de contacts à vérifier"
                )
            ),
            'required' => array('list_id')
        )
    ),
    array(
        'name' => 'get_blacklist',
        'description' => "Récupère la liste des numéros ayant envoyé STOP (liste noire / désabonnements). Ces numéros ne recevront plus de SMS marketing.",
        'inputSchema' => array(
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => array()
        )
    ),
    array(
        'name' => 'cancel_sms',
        'description' => "Annule un SMS programmé ou une campagne entière. Seuls les SMS en attente d'envoi peuvent être annulés. Les crédits sont automatiquement recrédités. IMPORTANT : le campaign_id est TOUJOURS requis (retourné par send_sms). Pour annuler un SMS spécifique, fournir aussi le sms_id en plus du campaign_id.",
        'inputSchema' => array(
            'type' => 'object',
            'properties' => array(
                'campaign_id' => array(
                    'type' => 'string',
                    'description' => "L'identifiant de la campagne à annuler"
                ),
                'sms_id' => array(
                    'type' => 'string',
                    'description' => "Optionnel. L'identifiant d'un SMS spécifique à annuler. Si non fourni, toute la campagne est annulée."
                )
            ),
            'required' => array('campaign_id')
        )
    ),
    array(
        'name' => 'generate_otp',
        'description' => "Génère et envoie un code OTP (One-Time Password) par SMS pour l'authentification à deux facteurs. Le code est valable quelques minutes.",
        'inputSchema' => array(
            'type' => 'object',
            'properties' => array(
                'to' => array(
                    'type' => 'string',
                    'description' => "Numéro du destinataire au format international (ex: '33612345678')"
                ),
                'sender' => array(
                    'type' => 'string',
                    'description' => "Nom de l'expéditeur"
                )
            ),
            'required' => array('to', 'sender')
        )
    ),
    array(
        'name' => 'verify_otp',
        'description' => "Vérifie un code OTP saisi par l'utilisateur. Retourne si le code est valide ou expiré.",
        'inputSchema' => array(
            'type' => 'object',
            'properties' => array(
                'to' => array(
                    'type' => 'string',
                    'description' => "Numéro du destinataire utilisé lors de la génération"
                ),
                'code' => array(
                    'type' => 'string',
                    'description' => "Le code OTP saisi par l'utilisateur"
                )
            ),
            'required' => array('to', 'code')
        )
    )
);

// ─── API Helper ──────────────────────────────────────────────────

function callApi($token, $payload)
{
    $endpoint = '';
    if(isset($payload['request']))
    {
        $endpoint = $payload['request'];
        unset($payload['request']);
    }
    $url = rtrim(API_URL, '/') . '/' . $endpoint;

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode((object)$payload),
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        )
    ));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if($response === false)
    {
        return array('status' => 'CURL_ERROR', 'error' => $err);
    }

    $json = json_decode($response, true);
    return $json ?: array('status' => 'PARSE_ERROR', 'raw' => substr($response, 0, 500));
}

// ─── Tool Handlers ───────────────────────────────────────────────

function handleTool($toolName, $args, $token)
{
    switch($toolName)
    {
        case 'send_sms':
            $payload = array(
                'request' => 'send_sms',
                'message' => array(
                    'sender' => $args['sender'],
                    'text'   => $args['message']
                ),
                'recipients' => $args['to']
            );
            if(!empty($args['delay']))
            {
                $payload['message']['delay'] = $args['delay'];
            }
            $result = callApi($token, $payload);
            if(isset($result['status']) && $result['status'] === 'SEND_OK')
            {
                return "SMS envoyé avec succès.\nCampagne ID : " . ($result['id'] ?? '?')
                     . "\nCrédits restants : " . ($result['credits'] ?? '?')
                     . "\nNombre de SMS : " . ($result['nbsms'] ?? '?')
                     . "\nDate : " . ($result['date'] ?? '?');
            }
            return "Erreur d'envoi : " . ($result['status'] ?? 'Inconnu') . "\n" . json_encode($result);

        case 'check_credits':
            $result = callApi($token, array('request' => 'credits'));
            if(isset($result['credits']))
            {
                return "Solde : " . $result['credits'] . " crédits SMS disponibles.";
            }
            return "Erreur : " . json_encode($result);

        case 'get_delivery_report':
            $result = callApi($token, array(
                'request'     => 'reports',
                'campaign_id' => $args['campaign_id']
            ));
            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        case 'get_responses':
            $result = callApi($token, array(
                'request'     => 'responses',
                'campaign_id' => $args['campaign_id']
            ));
            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        case 'verify_number':
            $result = callApi($token, array(
                'request'  => 'verify_numbers',
                'liste_id' => $args['list_id'],
                'action'   => 'check'
            ));
            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        case 'get_blacklist':
            $result = callApi($token, array('request' => 'blacklist'));
            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        case 'cancel_sms':
            $payload = array(
                'request'     => 'cancel',
                'campaign_id' => $args['campaign_id']
            );
            if(!empty($args['sms_id']))
            {
                $payload['sms_id'] = $args['sms_id'];
            }
            $result = callApi($token, $payload);
            if(isset($result['status']) && $result['status'] === 'CANCEL_OK')
            {
                return "Annulation réussie.\nCrédits recrédités. Nouveau solde : " . ($result['credits'] ?? '?') . " crédits.";
            }
            if(isset($result['status']) && $result['status'] === 'INVALID_SMS')
            {
                return "Erreur : SMS introuvable ou déjà envoyé.";
            }
            return "Erreur d'annulation : " . ($result['status'] ?? 'Inconnu') . "\n" . json_encode($result);

        case 'generate_otp':
            $result = callApi($token, array(
                'request' => 'generate_otp',
                'gsm'     => $args['to'],
                'sender'  => $args['sender']
            ));
            if(isset($result['status']) && $result['status'] === 'OTP_SENT')
            {
                return "Code OTP envoyé par SMS au " . $args['to'] . ". Demandez à l'utilisateur de saisir le code reçu.";
            }
            return "Erreur OTP : " . json_encode($result);

        case 'verify_otp':
            $result = callApi($token, array(
                'request' => 'verify_otp',
                'gsm'     => $args['to'],
                'code'    => $args['code']
            ));
            if(isset($result['status']) && $result['status'] === 'OTP_TRUE')
            {
                return "Code OTP valide. Identité confirmée.";
            }
            if(isset($result['status']) && $result['status'] === 'OTP_FALSE')
            {
                return "Code OTP invalide ou expiré.";
            }
            if(isset($result['status']) && $result['status'] === 'OTP_VERIFIED')
            {
                return "Ce code OTP a déjà été vérifié le " . ($result['verified_at'] ?? '?') . ".";
            }
            return "Erreur OTP : " . ($result['status'] ?? 'Inconnu');

        default:
            return "Outil inconnu : " . $toolName;
    }
}

// ─── MCP Protocol (JSON-RPC over stdin/stdout) ──────────────────

function sendResponse($id, $result)
{
    $response = array(
        'jsonrpc' => '2.0',
        'id'      => $id,
        'result'  => $result
    );
    $json = json_encode($response, JSON_UNESCAPED_UNICODE);
    fwrite(STDOUT, $json . "\n");
    fflush(STDOUT);
}

function sendError($id, $code, $message)
{
    $response = array(
        'jsonrpc' => '2.0',
        'id'      => $id,
        'error'   => array('code' => $code, 'message' => $message)
    );
    $json = json_encode($response, JSON_UNESCAPED_UNICODE);
    fwrite(STDOUT, $json . "\n");
    fflush(STDOUT);
}

function sendNotification($method, $params = null)
{
    $msg = array('jsonrpc' => '2.0', 'method' => $method);
    if($params !== null) $msg['params'] = $params;
    $json = json_encode($msg, JSON_UNESCAPED_UNICODE);
    fwrite(STDOUT, $json . "\n");
    fflush(STDOUT);
}

// ─── Main Loop ───────────────────────────────────────────────────

fwrite(STDERR, "[SMS Vert Pro MCP] Serveur démarré. En attente de connexion...\n");

while($line = fgets(STDIN))
{
    $line = trim($line);
    if(empty($line)) continue;

    $request = json_decode($line, true);
    if(!$request || !isset($request['method']))
    {
        continue;
    }

    $id     = isset($request['id']) ? $request['id'] : null;
    $method = $request['method'];
    $params = isset($request['params']) ? $request['params'] : array();

    switch($method)
    {
        // ── Initialisation ──
        case 'initialize':
            sendResponse($id, array(
                'protocolVersion' => '2024-11-05',
                'capabilities'   => array(
                    'tools' => new \stdClass()
                ),
                'serverInfo' => array(
                    'name'    => SERVER_NAME,
                    'version' => SERVER_VERSION
                )
            ));
            break;

        case 'notifications/initialized':
            // Notification client, pas de réponse nécessaire
            fwrite(STDERR, "[SMS Vert Pro MCP] Connexion établie.\n");
            break;

        // ── Liste des outils ──
        case 'tools/list':
            sendResponse($id, array('tools' => $tools));
            break;

        // ── Appel d'un outil ──
        case 'tools/call':
            $toolName = isset($params['name']) ? $params['name'] : '';
            $toolArgs = isset($params['arguments']) ? $params['arguments'] : array();

            fwrite(STDERR, "[SMS Vert Pro MCP] Appel outil : $toolName\n");

            $text = handleTool($toolName, $toolArgs, $apiToken);

            sendResponse($id, array(
                'content' => array(
                    array('type' => 'text', 'text' => $text)
                )
            ));
            break;

        // ── Ping ──
        case 'ping':
            sendResponse($id, new \stdClass());
            break;

        // ── Méthode inconnue ──
        default:
            if($id !== null)
            {
                sendError($id, -32601, 'Méthode non supportée : ' . $method);
            }
            break;
    }
}
