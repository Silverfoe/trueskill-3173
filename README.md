# TrueSkill FRC Prediction API — README

> A **local-only** HTTP API for FRC match predictions using **Microsoft TrueSkill** and **The Blue Alliance** (TBA).
> Built for speed, simplicity, and reliability—plus a tiny PHP tester you can serve locally.

---

## What you get

* **Local API (Flask)** on `http://127.0.0.1:5000`
* **End-to-end event rebuild** from TBA: `/update`
* **Incremental live updates**: `/push_results`
* **Win probabilities** for alliances: `/predict_match`, `/predict_batch`
* **Team rating lookup**: `/predict_team`
* **Health check**: `/health`
* **Minimal PHP tester** (works even without PHP cURL) to drive the API from a browser

> **Security-first**: The server binds to `127.0.0.1` only.
> **TrueSkill**: Multi-player teams, ties supported, with proper win probability per trueskill.org.
> **Event key validation**: must include the year, e.g. `2025nyrr`.

---

## Prerequisites

* **Python** 3.8+
* **pip** packages:

  ```bash
  pip install flask requests trueskill
  ```
* **The Blue Alliance** API key (Read API). Set it in the code before you run.

* **Do NOT** name your API file `trueskill.py`.
  Use `trueskill_api.py` (naming it `trueskill.py` shadows the library and breaks imports).

---

## Run the API (localhost only)

```bash
python trueskill_api.py
```

Verify it’s up:

```bash
curl -s http://127.0.0.1:5000/health | jq
```

Expected:

```json
{
  "ok": true,
  "teams_indexed": 0
}
```

---

## Endpoint Reference

### 1) Rebuild from TBA for an event

* **POST** `/update`
* **Body**: `{"event_key": "2025nyrr"}`
* **Notes**: Clears in-memory ratings and **replays all matches** from the event.

```bash
curl -s -X POST http://127.0.0.1:5000/update \
  -H "Content-Type: application/json" \
  -d '{"event_key":"2025nyrr"}' | jq
```

**Response (example):**

```json
{
  "status": "rankings updated",
  "teams_indexed": 74,
  "event_key": "2025nyrr"
}
```

> **Event key format**: `^\d{4}[a-z0-9]+$` — e.g., `2025nyny`, `2025nyrr`.
> If invalid, you’ll get a **400** with a clear error.

---

### 2) ➕ Push live results (incremental, no TBA call)

* **POST** `/push_results`
* **Body**: array of match objects

```bash
curl -s -X POST http://127.0.0.1:5000/push_results \
  -H "Content-Type: application/json" \
  -d '[
        {
          "teams1": ["frc254","frc1678","frc118"],
          "teams2": ["frc1323","frc2056","frc148"],
          "score1": 120,
          "score2": 95
        },
        {
          "teams1": ["frc1678","frc254","frc118"],
          "teams2": ["frc2056","frc1323","frc148"],
          "score1": 87,
          "score2": 87
        }
      ]' | jq
```

**Response:**

```json
{
  "status": "results incorporated",
  "applied": 2
}
```

---

### 3) Team rating lookup

* **GET** `/predict_team?team=frc254`

```bash
curl -s "http://127.0.0.1:5000/predict_team?team=frc254" | jq
```

**Response (example):**

```json
{
  "team": "frc254",
  "mu": 29.12,
  "sigma": 7.23
}
```

---

### 4) Predict one match (win probabilities)

* **POST** `/predict_match`
* **Body**: `{"teams1":[...], "teams2":[...]}`

```bash
curl -s -X POST http://127.0.0.1:5000/predict_match \
  -H "Content-Type: application/json" \
  -d '{"teams1":["frc254","frc1678","frc118"],"teams2":["frc1323","frc2056","frc148"]}' | jq
```

**Response (example):**

```json
{
  "team1_win_prob": 0.6429187735,
  "team2_win_prob": 0.3570812265
}
```

---

### 5) Batch predictions

* **POST** `/predict_batch`
* **Body**: array of match specs

```bash
curl -s -X POST http://127.0.0.1:5000/predict_batch \
  -H "Content-Type: application/json" \
  -d '[
        {
          "teams1":["frc254","frc1678","frc118"],
          "teams2":["frc1323","frc2056","frc148"]
        },
        {
          "teams1":["frc1114","frc2056","frc1241"],
          "teams2":["frc33","frc217","frc910"]
        }
      ]' | jq
```

**Response (example):**

```json
[
  {
    "teams1": ["frc254","frc1678","frc118"],
    "teams2": ["frc1323","frc2056","frc148"],
    "team1_win_prob": 0.6429187735,
    "team2_win_prob": 0.3570812265
  },
  {
    "teams1": ["frc1114","frc2056","frc1241"],
    "teams2": ["frc33","frc217","frc910"],
    "team1_win_prob": 0.513922184,
    "team2_win_prob": 0.486077816
  }
]
```

---

## Optional: PHP Tester (local UI)

A tiny PHP page to drive the API from a browser. It should fall back to `file_get_contents()` if PHP cURL is missing.

1. Save as `trueskill_client.php` and serve it:

```bash
php -S 127.0.0.1:8080
```

2. Open:

```
http://127.0.0.1:8080/trueskill_client.php
```

3. Use the forms to:

* Rebuild from TBA
* Push results
* Get team rating
* Predict single or batch matches

>  If you see `Call to undefined function curl_init()`, either enable PHP cURL **or** rely on the built-in fallback (ensure `allow_url_fopen=On` in `php.ini`).

---

## Troubleshooting

* **ImportError / circular import**
  **Cause**: File named `trueskill.py`.
  **Fix**: Rename to `trueskill_api.py`.

* **400 “Invalid event_key …”**
  **Cause**: Missing year (`nyrr` instead of `2025nyrr`).
  **Fix**: Include the 4-digit year.

* **401/403 from TBA**
  **Cause**: Bad/missing TBA key.
  **Fix**: `export TBA_AUTH_KEY="your_key"` and restart.

* **“no matches found”**
  **Cause**: Event exists, but no published matches.
  **Fix**: Confirm the event or try later.

* **PHP: curl_init undefined**
  **Fix**: Enable PHP cURL *or* rely on fallback; ensure `allow_url_fopen=On`.

* **Still feels 50/50**
  **Cause**: Not enough results yet; ratings near default.
  **Fix**: Rebuild with `/update` or push more completed matches.

---

## How fast is it?

It’s usually network-bound by TBA plus linear TrueSkill updates.

**Measure it on your box:**

```bash
curl -w "\ntotal: %{time_total}s\n" -s -X POST http://127.0.0.1:5000/update \
  -H "Content-Type: application/json" \
  -d '{"event_key":"2025nyrr"}' -o /dev/null
```

(If you want the API to **report** duration, wrap the update logic with a timer and return `duration_seconds` in JSON.)

---

## Tips & Best Practices

* Use **TBA team keys** with the prefix: `frc####`.
* For **live events**, prefer **incremental** `/push_results` between rebuilds.
* The server is **local-only** by default. If you bind beyond `127.0.0.1`, add your own auth/controls.

---

## General Project Layout

```
.
├─ trueskill_api.py         # Flask API (localhost only)
└─ trueskill_client.php     # Optional PHP tester (localhost)
```

---

## Quickstart

```bash
# 1) Install deps
pip install flask requests trueskill

# 2) Set TBA API key in code.

# 3) Run API
python trueskill_api.py

# 4) Sanity check
curl -s http://127.0.0.1:5000/health | jq

# 5) Rebuild from TBA
curl -s -X POST http://127.0.0.1:5000/update \
  -H "Content-Type: application/json" \
  -d '{"event_key":"2025nyrr"}' | jq

# 6) Predict a match
curl -s -X POST http://127.0.0.1:5000/predict_match \
  -H "Content-Type: application/json" \
  -d '{"teams1":["frc254","frc1678","frc118"],"teams2":["frc1323","frc2056","frc148"]}' | jq
```

---

**Happy Gambling!**
