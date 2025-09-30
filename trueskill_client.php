<?php
/*  trueskill_client.php
    Minimal PHP front-end for your local Flask API on 127.0.0.1:5000.
    Run with: php -S 127.0.0.1:8080 (then open http://127.0.0.1:8080/trueskill_client.php)
*/
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0'); // keep UI clean; errors shown as JSON below

$DEFAULT_BASE = 'http://127.0.0.1:5000';

function call_api(string $method, string $url, $data = null): array {
    $ch = curl_init();
    $headers = ['Content-Type: application/json'];
    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_TIMEOUT        => 30,
    ];
    if ($data !== null) {
        $opts[CURLOPT_POSTFIELDS] = is_string($data) ? $data : json_encode($data);
    }
    curl_setopt_array($ch, $opts);
    $raw  = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    $status = (int)($info['http_code'] ?? 0);
    $decoded = null;
    if (is_string($raw) && $raw !== '') {
        $tmp = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) $decoded = $tmp;
    }
    return [
        'ok'     => ($err === '' && $status >= 200 && $status < 300 && $decoded !== null),
        'status' => $status,
        'json'   => $decoded,
        'raw'    => $raw,
        'error'  => $err,
    ];
}

function pretty($x): string {
    if (is_array($x)) return json_encode($x, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $tmp = json_decode((string)$x, true);
    if (json_last_error() === JSON_ERROR_NONE) return json_encode($tmp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return (string)$x;
}

function parse_alliance(?string $s): array {
    $s = trim((string)$s);
    if ($s === '') return [];
    $parts = preg_split('/[,\s]+/', $s);
    return array_values(array_filter(array_map('trim', $parts), fn($x) => $x !== ''));
}

$base    = isset($_POST['base']) && $_POST['base'] !== '' ? $_POST['base'] : $DEFAULT_BASE;
$action  = $_POST['action'] ?? '';
$result  = null;
$message = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'update') {
            $eventKey = trim($_POST['event_key'] ?? '');
            if ($eventKey === '') throw new Exception('Please enter an event key (e.g., 2025nyrr).');
            if (!preg_match('/^\d{4}[a-z0-9]+$/i', $eventKey)) {
                throw new Exception('Invalid event key. Use full key with year, e.g., 2025nyrr.');
            }
            $result = call_api('POST', rtrim($base, '/') . '/update', ['event_key' => $eventKey]);

        } elseif ($action === 'push') {
            $payload = $_POST['push_json'] ?? '';
            if ($payload === '') throw new Exception('Paste a JSON array payload.');
            $decoded = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new Exception('Invalid JSON array.');
            }
            $result = call_api('POST', rtrim($base, '/') . '/push_results', $decoded);

        } elseif ($action === 'team') {
            $team = trim($_POST['team_key'] ?? '');
            if ($team === '') throw new Exception('Provide a team key like frc254.');
            $result = call_api('GET', rtrim($base, '/') . '/predict_team?team=' . urlencode($team));

        } elseif ($action === 'predict_one') {
            $t1 = parse_alliance($_POST['teams1'] ?? '');
            $t2 = parse_alliance($_POST['teams2'] ?? '');
            if (!$t1 || !$t2) throw new Exception('Enter both alliances.');
            $result = call_api('POST', rtrim($base, '/') . '/predict_match', ['teams1' => $t1, 'teams2' => $t2]);

        } elseif ($action === 'predict_batch') {
            $payload = $_POST['batch_json'] ?? '';
            if ($payload === '') throw new Exception('Paste a JSON array payload.');
            $decoded = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new Exception('Invalid JSON array.');
            }
            $result = call_api('POST', rtrim($base, '/') . '/predict_batch', $decoded);
        }
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
}

function box($result, $message): string {
    if ($message !== null) {
        return '<div class="log err">'.htmlspecialchars($message).'</div>';
    }
    if ($result === null) return '';
    $cls = ($result['ok'] ? 'ok' : 'err');
    $body = $result['json'] ?? $result['raw'] ?? '';
    return '<div class="log '.$cls.'">'.htmlspecialchars(pretty($body)).'</div>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>TrueSkill FRC – PHP Test Client (Local)</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.4; }
  h1 { margin-top: 0; }
  fieldset { margin-bottom: 18px; border: 1px solid #ccc; padding: 12px; }
  legend { padding: 0 6px; }
  label { display: block; margin: 8px 0 4px; }
  input[type=text], textarea { width: 100%; box-sizing: border-box; padding: 8px; }
  button { padding: 8px 12px; cursor: pointer; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .log { white-space: pre-wrap; background: #f7f7f7; border: 1px solid #ddd; padding: 10px; min-height: 110px; }
  .ok { color: #0a7f2e; }
  .err { color: #b00020; }
  small { color: #555; }
</style>
</head>
<body>
  <h1>TrueSkill FRC – PHP Test Client</h1>
  <p><small>Make sure the Python API is running at the base URL below.</small></p>
  <form method="post">
    <fieldset>
      <legend>Settings</legend>
      <label for="base">API Base URL</label>
      <input id="base" name="base" type="text" value="<?php echo htmlspecialchars($base); ?>">
    </fieldset>

    <fieldset>
      <legend>1) Rebuild from TBA Event</legend>
      <label for="event_key">Event Key (e.g., <code>2025nyrr</code>)</label>
      <input id="event_key" name="event_key" type="text" placeholder="2025nyrr" />
      <button name="action" value="update">Rebuild Ratings</button>
      <?php echo box(($action==='update' ? $result : null), $message); ?>
      <small>Clears in-memory ratings and rebuilds from the event’s matches.</small>
    </fieldset>

    <fieldset>
      <legend>2) Push Live Results (Incremental)</legend>
      <small>JSON array of matches. Example:</small>
      <pre class="log">[
  {"teams1":["frc254","frc1678","frc118"],"teams2":["frc1323","frc2056","frc148"],"score1":120,"score2":95}
]</pre>
      <textarea name="push_json" rows="8" placeholder='[{"teams1":["frc254","frc1678","frc118"],"teams2":["frc1323","frc2056","frc148"],"score1":120,"score2":95}]'></textarea>
      <button name="action" value="push">Push Results</button>
      <?php echo box(($action==='push' ? $result : null), $message); ?>
    </fieldset>

    <div class="grid">
      <fieldset>
        <legend>3) Team Rating</legend>
        <label for="team_key">Team Key (e.g., <code>frc254</code>)</label>
        <input id="team_key" name="team_key" type="text" placeholder="frc254" />
        <button name="action" value="team">Get Rating</button>
        <?php echo box(($action==='team' ? $result : null), $message); ?>
      </fieldset>

      <fieldset>
        <legend>4) Predict One Match</legend>
        <label for="teams1">Alliance 1 (space or comma separated)</label>
        <input id="teams1" name="teams1" type="text" placeholder="frc254, frc1678, frc118" />
        <label for="teams2">Alliance 2 (space or comma separated)</label>
        <input id="teams2" name="teams2" type="text" placeholder="frc1323, frc2056, frc148" />
        <button name="action" value="predict_one">Predict</button>
        <?php echo box(($action==='predict_one' ? $result : null), $message); ?>
      </fieldset>
    </div>

    <fieldset>
      <legend>5) Batch Predictions</legend>
      <small>JSON array of matches. Example:</small>
      <pre class="log">[
  { "teams1":["frc254","frc1678","frc118"], "teams2":["frc1323","frc2056","frc148"] },
  { "teams1":["frc1114","frc2056","frc1241"], "teams2":["frc33","frc217","frc910"] }
]</pre>
      <textarea name="batch_json" rows="8" placeholder='[{"teams1":["frc254","frc1678","frc118"],"teams2":["frc1323","frc2056","frc148"]}]'></textarea>
      <button name="action" value="predict_batch">Predict Batch</button>
      <?php echo box(($action==='predict_batch' ? $result : null), $message); ?>
    </fieldset>
  </form>

  <p><small>
    Tips: 1) Keep the API and this page on the same machine. 2) Use TBA-style team keys (<code>frc####</code>). 3) Event key must include the year (e.g., <code>2025nyrr</code>).
  </small></p>
</body>
</html>
