<?php
// ================================================================
//  agent.php — Cheesy Burgers AI Ordering Agent
//  Place in: xampp/htdocs/cheesyburgers/agent.php
//  Works with: db_config.php, auth.php, cart_action.php
// ================================================================
session_start();
include 'db_config.php';

// ── Load LIVE menu from database ─────────────────────────────
$menu_by_cat = [];
$all_menu = [];
$res = $conn->query("SELECT * FROM menu WHERE avail=1 ORDER BY cat, name");
while ($r = $res->fetch_assoc()) {
  $menu_by_cat[$r['cat']][] = $r;
  $all_menu[] = $r;
}

// ── Session info ─────────────────────────────────────────────
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? 'Guest';
$userInitials = $isLoggedIn ? mb_strtoupper(mb_substr($userName, 0, 2)) : 'GU';
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$cart = $_SESSION['cart'] ?? [];
$cartCount = array_sum(array_column($cart, 'qty'));

// ── Build menu JSON for JS ────────────────────────────────────
$menu_json = json_encode($all_menu, JSON_UNESCAPED_UNICODE);

// ── Load last 5 orders for logged-in user ───────────────────
$user_orders = [];
if ($isLoggedIn) {
  $uid = (int) $_SESSION['user_id'];
  $stmt = $conn->prepare(
    "SELECT id, items, total, status, time FROM orders
         WHERE customer_id = ? ORDER BY time DESC LIMIT 5"
  );
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $res2 = $stmt->get_result();
  while ($o = $res2->fetch_assoc())
    $user_orders[] = $o;
  $stmt->close();
}
$orders_json = json_encode($user_orders, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🧀 CheeseBot — AI Agent | Cheesy Burgers</title>
  <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box
    }

    :root {
      --cheese: #F4A800;
      --cheese-dark: #C97D00;
      --cheese-light: #FFD966;
      --melt: #FF6B00;
      --bg: #0A0500;
      --surface: #130900;
      --surface2: #1C0E00;
      --surface3: #241400;
      --text: #FFE8B0;
      --muted: #8A6040;
      --border: rgba(244, 168, 0, 0.13);
      --border2: rgba(244, 168, 0, 0.24);
      --green: #22c55e;
      --green-bg: rgba(34, 197, 94, 0.10);
      --green-bd: rgba(34, 197, 94, 0.25);
      --red: #ef4444;
      --red-bg: rgba(239, 68, 68, 0.10);
      --blue: #3b82f6;
      --purple: #a855f7;
      --user-bbl: #7c3d00;
      --bot-bbl: #1C0E00;
    }

    [data-theme="light"] {
      --bg: #FFFDF5;
      --surface: #fff;
      --surface2: #FFFBF0;
      --surface3: #FFF5D6;
      --text: #3D1F00;
      --muted: #8A6040;
      --border: rgba(244, 168, 0, 0.2);
      --border2: rgba(244, 168, 0, 0.35);
      --user-bbl: #C97D00;
      --bot-bbl: #FFF5D6;
    }

    html,
    body {
      height: 100%;
      font-family: 'Nunito', sans-serif;
      background: var(--bg);
      color: var(--text);
      transition: background .3s, color .3s;
      overflow: hidden
    }

    .shell {
      display: flex;
      height: 100vh
    }

    /* ── SPLIT SCREEN CALL PANEL — Full screen mobile-first ── */
    #callPanel {
      width: 0;
      overflow: hidden;
      flex-shrink: 0;
      background: var(--surface);
      border-left: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      transition: width .35s cubic-bezier(.4, 0, .2, 1);
      position: relative;
    }

    .shell.call-active #callPanel {
      width: 360px
    }

    .shell.call-active .chat-area {
      min-width: 0
    }

    @media(max-width:900px) {
      .shell.call-active .sidebar {
        display: none
      }

      .shell.call-active #callPanel {
        width: 320px
      }
    }

    @media(max-width:640px) {
      .shell.call-active {
        flex-direction: column
      }

      .shell.call-active .chat-area {
        flex: 1;
        min-height: 0
      }

      .shell.call-active #callPanel {
        width: 100% !important;
        height: 340px;
        flex-shrink: 0;
        border-left: none;
        border-top: 2px solid var(--border2);
      }
    }

    /* Activity ticker bar at top */
    .cp-activity-bar {
      width: 100%;
      background: linear-gradient(90deg, rgba(244, 168, 0, .07), rgba(255, 107, 0, .07));
      border-bottom: 1px solid var(--border2);
      padding: 6px 10px;
      display: flex;
      align-items: center;
      gap: 8px;
      overflow: hidden;
      flex-shrink: 0;
    }

    .cp-act-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--green);
      flex-shrink: 0;
      animation: blink 1.4s infinite;
    }

    .cp-act-text {
      font-size: 10px;
      font-weight: 700;
      color: var(--cheese-light);
      white-space: nowrap;
      display: inline-block;
      animation: tickerScroll 28s linear infinite;
    }

    @keyframes tickerScroll {
      0% {
        transform: translateX(100%)
      }

      100% {
        transform: translateX(-100%)
      }
    }

    .cp-inner {
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 100%;
      flex: 1;
      overflow: hidden;
    }

    /* Top info strip */
    .cp-top-strip {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: .9rem 1rem .6rem;
      flex-shrink: 0;
      width: 100%;
    }

    .cp-label {
      font-size: 9px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: var(--green);
      background: var(--green-bg);
      border: 1px solid var(--green-bd);
      border-radius: 20px;
      padding: 3px 12px;
      margin-bottom: .75rem;
    }

    .cp-avatar {
      width: 68px;
      height: 68px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--cheese), var(--melt));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      margin-bottom: .5rem;
      box-shadow: 0 0 0 0 rgba(244, 168, 0, .4);
      animation: cpPulse 2s ease-in-out infinite;
    }

    @keyframes cpPulse {

      0%,
      100% {
        box-shadow: 0 0 0 0 rgba(244, 168, 0, .4)
      }

      50% {
        box-shadow: 0 0 0 14px rgba(244, 168, 0, .0)
      }
    }

    .cp-avatar.speaking {
      animation: cpSpeak .45s ease-in-out infinite alternate
    }

    @keyframes cpSpeak {
      from {
        transform: scale(1)
      }

      to {
        transform: scale(1.08)
      }
    }

    .cp-name {
      font-family: 'Fredoka One', cursive;
      font-size: 1.05rem;
      color: var(--text);
      margin-bottom: 2px;
    }

    .cp-status {
      font-size: 11px;
      font-weight: 700;
      color: var(--muted);
      margin-bottom: .3rem
    }

    .cp-timer {
      font-family: 'Fredoka One', cursive;
      font-size: 1.3rem;
      color: var(--cheese);
      letter-spacing: 2px;
    }

    /* Scrollable chat transcript in call panel */
    .cp-chat-scroll {
      flex: 1;
      overflow-y: auto;
      width: 100%;
      padding: .5rem .85rem;
      display: flex;
      flex-direction: column;
      gap: .45rem;
      scrollbar-width: thin;
      scrollbar-color: rgba(244, 168, 0, .1) transparent;
    }

    .cp-bot-bubble {
      background: var(--surface2);
      border: 1px solid var(--border2);
      border-radius: 4px 12px 12px 12px;
      padding: .6rem .85rem;
      font-size: 12.5px;
      line-height: 1.6;
      color: var(--text);
      font-weight: 500;
      animation: fadeUp .25s ease;
    }

    .cp-user-bubble {
      background: rgba(124, 61, 0, .35);
      border: 1px solid rgba(244, 168, 0, .18);
      border-radius: 12px 4px 12px 12px;
      padding: .5rem .85rem;
      font-size: 12px;
      color: var(--cheese-light);
      font-weight: 600;
      font-style: italic;
      text-align: right;
      animation: fadeUp .25s ease;
    }

    .cp-user-text {
      display: none
    }

    /* kept for JS compat */

    /* Bottom bar: waveform + controls */
    .cp-bottom-bar {
      width: 100%;
      background: var(--surface2);
      border-top: 1px solid var(--border2);
      padding: .65rem 1rem .8rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: .55rem;
      flex-shrink: 0;
    }

    /* Waveform */
    .cp-wave {
      display: flex;
      align-items: center;
      gap: 3px;
      height: 44px;
      width: 100%;
      justify-content: center;
    }

    .cp-bar {
      width: 3px;
      border-radius: 3px;
      background: var(--cheese);
      transition: height .08s ease;
    }

    .cp-bar.idle {
      height: 4px !important;
      background: var(--muted) !important;
      animation: none !important;
    }

    .cp-bar.listening {
      background: var(--green) !important;
    }

    /* Control row */
    .cp-controls {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 1.6rem;
      width: 100%;
    }

    .cp-ctrl-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
      background: var(--surface3);
      border: 1.5px solid var(--border2);
      border-radius: 16px;
      padding: 10px 20px;
      cursor: pointer;
      font-size: 1.3rem;
      transition: all .2s;
      color: var(--text);
      font-family: 'Nunito', sans-serif;
    }

    .cp-ctrl-btn:hover {
      border-color: var(--cheese);
      background: rgba(244, 168, 0, .1)
    }

    .cp-ctrl-btn.active {
      border-color: var(--melt);
      background: rgba(255, 107, 0, .12)
    }

    .cp-end-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 3px;
      width: 66px;
      height: 66px;
      border-radius: 50%;
      background: var(--red);
      border: none;
      cursor: pointer;
      font-size: 1.7rem;
      box-shadow: 0 4px 22px rgba(239, 68, 68, .5);
      transition: all .2s;
      color: #fff;
    }

    .cp-end-btn:hover {
      transform: scale(1.1);
      background: #dc2626
    }

    .cp-ctrl-lbl {
      font-size: 9px;
      font-weight: 800;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: var(--muted);
      font-family: 'Nunito', sans-serif;
    }

    /* ── Activity ticker ── */
    .cp-activity-bar {
      width: 100%;
      background: rgba(244,168,0,0.08);
      border-bottom: 1px solid var(--border2);
      padding: 5px 10px;
      display: flex;
      align-items: center;
      gap: 8px;
      overflow: hidden;
      flex-shrink: 0;
    }
    .cp-act-dot {
      width: 7px; height: 7px; border-radius: 50%;
      background: var(--green);
      flex-shrink: 0;
      animation: blink 1.4s infinite;
    }
    .cp-act-scroll {
      font-size: 10px; font-weight: 700;
      color: var(--cheese-light);
      white-space: nowrap;
      animation: tickerScroll 22s linear infinite;
    }
    @keyframes tickerScroll {
      from { transform: translateX(100%); }
      to   { transform: translateX(-100%); }
    }
    /* ── Chat scroll inside call panel ── */
    .cp-chat-scroll {
      flex: 1;
      overflow-y: auto;
      width: 100%;
      padding: .5rem .75rem;
      display: flex;
      flex-direction: column;
      gap: .5rem;
      scrollbar-width: thin;
      scrollbar-color: rgba(244,168,0,.1) transparent;
    }
    .cp-bot-bubble {
      background: var(--surface2);
      border: 1px solid var(--border2);
      border-radius: 12px 12px 12px 4px;
      padding: .7rem .9rem;
      font-size: 12.5px;
      line-height: 1.6;
      color: var(--text);
      font-weight: 500;
    }
    .cp-user-text {
      font-size: 12px;
      color: var(--cheese-light);
      font-weight: 700;
      font-style: italic;
      text-align: right;
      padding-right: 4px;
    }
    /* ── Bottom bar: waveform + controls ── */
    .cp-bottom-bar {
      width: 100%;
      background: var(--surface2);
      border-top: 1px solid var(--border2);
      padding: .75rem 1rem .85rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: .65rem;
      flex-shrink: 0;
    }
    .cp-wave {
      display: flex;
      align-items: center;
      gap: 3px;
      height: 42px;
      width: 100%;
      justify-content: center;
    }
    .cp-bar {
      width: 3px;
      border-radius: 3px;
      background: var(--cheese);
      transition: height .08s ease;
    }
    .cp-bar.idle { height: 4px; background: var(--muted); animation: none !important; }
    .cp-bar.listening { background: var(--green); }
    .cp-controls {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 1.8rem;
      width: 100%;
    }
    .cp-ctrl-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 3px;
      background: var(--surface3);
      border: 1px solid var(--border2);
      border-radius: 14px;
      padding: 10px 18px;
      cursor: pointer;
      font-size: 1.3rem;
      transition: all .2s;
      color: var(--text);
    }
    .cp-ctrl-btn:hover { border-color: var(--cheese); background: rgba(244,168,0,.1); }
    .cp-ctrl-btn.active { border-color: var(--melt); background: rgba(255,107,0,.12); }
    .cp-end-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 3px;
      width: 64px;
      height: 64px;
      border-radius: 50%;
      background: var(--red);
      border: none;
      cursor: pointer;
      font-size: 1.6rem;
      box-shadow: 0 4px 20px rgba(239,68,68,.5);
      transition: all .2s;
      color: #fff;
      justify-content: center;
    }
    .cp-end-btn:hover { transform: scale(1.1); background: #dc2626; }
    .cp-ctrl-lbl {
      font-size: 9px;
      font-weight: 800;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: var(--muted);
      font-family: 'Nunito', sans-serif;
    }

    @keyframes waveAnim {
      from {
        transform: scaleY(1)
      }

      to {
        transform: scaleY(2.4)
      }
    }

    /* ── SIDEBAR ── */
    .sidebar {
      width: 268px;
      flex-shrink: 0;
      background: var(--surface);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column
    }

    .sb-top {
      padding: 1rem;
      border-bottom: 1px solid var(--border);
      flex-shrink: 0
    }

    .brand {
      font-family: 'Fredoka One', cursive;
      font-size: 1.2rem;
      color: var(--cheese);
      display: flex;
      align-items: center;
      gap: 6px
    }

    .brand-sub {
      font-size: 9px;
      color: var(--melt);
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 2px;
      margin-top: 2px
    }

    .live-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      margin-top: .65rem;
      background: var(--green-bg);
      border: 1px solid var(--green-bd);
      border-radius: 20px;
      padding: 3px 10px
    }

    .ldot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--green);
      animation: blink 1.4s infinite;
      flex-shrink: 0
    }

    @keyframes blink {
      50% {
        opacity: .2;
        transform: scale(1.5)
      }
    }

    .live-txt {
      font-size: 10px;
      font-weight: 800;
      color: var(--green)
    }

    .sb-user {
      display: flex;
      align-items: center;
      gap: 9px;
      padding: .75rem 1rem;
      border-bottom: 1px solid var(--border)
    }

    .sb-av {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--cheese), var(--melt));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: 800;
      color: #1A0A00;
      flex-shrink: 0
    }

    .sb-uname {
      font-size: 12.5px;
      font-weight: 800;
      color: var(--text)
    }

    .sb-utag {
      font-size: 10px;
      color: var(--muted);
      font-weight: 600;
      margin-top: 1px
    }

    .sb-scroll {
      flex: 1;
      overflow-y: auto;
      padding: .65rem;
      scrollbar-width: thin;
      scrollbar-color: rgba(244, 168, 0, .1) transparent
    }

    .sb-lbl {
      font-size: 9px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: var(--muted);
      margin: .75rem 0 .3rem .2rem
    }

    .qb {
      width: 100%;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 9px;
      padding: 8px 10px;
      text-align: left;
      color: var(--text);
      font-family: 'Nunito', sans-serif;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: all .2s;
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 4px
    }

    .qb:hover {
      background: rgba(244, 168, 0, .08);
      border-color: var(--cheese);
      color: var(--cheese)
    }

    .qb .i {
      font-size: 1rem;
      min-width: 18px;
      text-align: center;
      flex-shrink: 0
    }

    .qb .badge {
      margin-left: auto;
      background: var(--melt);
      color: #fff;
      font-size: 9px;
      font-weight: 800;
      padding: 1px 6px;
      border-radius: 7px
    }

    .mini-cat {
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 9px;
      padding: .6rem .75rem;
      margin-bottom: 4px
    }

    .mc-h {
      font-size: 10px;
      font-weight: 800;
      color: var(--cheese);
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 4px
    }

    .mc-r {
      display: flex;
      justify-content: space-between;
      font-size: 11px;
      font-weight: 600;
      color: var(--muted);
      padding: 1.5px 0;
      cursor: pointer;
      transition: color .15s
    }

    .mc-r:hover {
      color: var(--cheese)
    }

    .mc-r span:last-child {
      color: var(--cheese-dark);
      font-family: 'Fredoka One', cursive;
      font-size: 10.5px
    }

    .promo-box {
      background: linear-gradient(135deg, rgba(244, 168, 0, .07), rgba(255, 107, 0, .07));
      border: 1px solid rgba(244, 168, 0, .18);
      border-radius: 9px;
      padding: .6rem .75rem;
      margin-bottom: 4px
    }

    .promo-codes {
      font-size: 11px;
      font-weight: 700;
      color: var(--cheese);
      line-height: 2
    }

    .hist-card {
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 9px;
      padding: .55rem .75rem;
      margin-bottom: 4px;
      cursor: pointer;
      transition: all .2s
    }

    .hist-card:hover {
      border-color: var(--cheese)
    }

    .hc-id {
      font-size: 10px;
      font-weight: 800;
      color: var(--cheese)
    }

    .hc-info {
      font-size: 11px;
      font-weight: 600;
      color: var(--muted);
      margin-top: 1px
    }

    .hc-st {
      font-size: 9px;
      font-weight: 800;
      padding: 2px 6px;
      border-radius: 6px;
      display: inline-block;
      margin-top: 2px
    }

    .st-p {
      background: rgba(249, 115, 22, .12);
      color: #f97316
    }

    .st-c {
      background: rgba(59, 130, 246, .12);
      color: var(--blue)
    }

    .st-o {
      background: rgba(168, 85, 247, .12);
      color: var(--purple)
    }

    .st-d {
      background: var(--green-bg);
      color: var(--green)
    }

    .sb-bottom {
      padding: .75rem;
      border-top: 1px solid var(--border);
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      gap: 3px
    }

    .dm-row {
      display: flex;
      align-items: center;
      gap: 9px;
      width: 100%;
      background: none;
      border: none;
      cursor: pointer;
      font-family: 'Nunito', sans-serif;
      padding: 5px 2px
    }

    .dm-lbl {
      font-size: 12px;
      font-weight: 600;
      color: var(--muted);
      flex: 1;
      text-align: left
    }

    .dm-pill {
      width: 34px;
      height: 19px;
      background: rgba(255, 255, 255, .1);
      border-radius: 19px;
      position: relative;
      transition: background .3s;
      flex-shrink: 0
    }

    .dm-pill::after {
      content: '';
      position: absolute;
      top: 2.5px;
      left: 2.5px;
      width: 14px;
      height: 14px;
      background: #fff;
      border-radius: 50%;
      transition: transform .3s
    }

    [data-theme="dark"] .dm-pill {
      background: var(--cheese)
    }

    [data-theme="dark"] .dm-pill::after {
      transform: translateX(15px)
    }

    .sb-lnk {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 5px 2px;
      font-size: 12px;
      font-weight: 600;
      color: var(--muted);
      cursor: pointer;
      background: none;
      border: none;
      width: 100%;
      font-family: 'Nunito', sans-serif;
      transition: color .2s;
      text-decoration: none
    }

    .sb-lnk:hover {
      color: var(--cheese-light)
    }

    /* ── CHAT AREA ── */
    .chat-area {
      flex: 1;
      display: flex;
      flex-direction: column;
      min-width: 0;
      position: relative
    }

    .topbar {
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      padding: .7rem 1.1rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
      gap: 10px
    }

    .tb-l {
      display: flex;
      align-items: center;
      gap: 10px
    }

    .bot-av {
      width: 38px;
      height: 38px;
      background: linear-gradient(135deg, var(--cheese), var(--melt));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      flex-shrink: 0;
      position: relative
    }

    .bot-av::after {
      content: '';
      position: absolute;
      bottom: 1px;
      right: 1px;
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: var(--green);
      border: 2px solid var(--surface)
    }

    .bot-name {
      font-family: 'Fredoka One', cursive;
      font-size: .95rem;
      color: var(--text);
      line-height: 1
    }

    .bot-sub {
      font-size: 10px;
      color: var(--muted);
      font-weight: 600;
      margin-top: 1px
    }

    .tb-r {
      display: flex;
      gap: 6px;
      align-items: center
    }

    .ib {
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 5px 10px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 11px;
      font-weight: 700;
      color: var(--muted);
      font-family: 'Nunito', sans-serif;
      transition: all .2s;
      white-space: nowrap
    }

    .ib:hover {
      background: rgba(244, 168, 0, .1);
      color: var(--cheese);
      border-color: var(--cheese)
    }

    .ib.on {
      background: rgba(244, 168, 0, .12);
      color: var(--cheese);
      border-color: var(--cheese)
    }

    .back-btn {
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 5px 10px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 11px;
      font-weight: 700;
      color: var(--muted);
      text-decoration: none;
      transition: all .2s
    }

    .back-btn:hover {
      color: var(--cheese);
      border-color: var(--cheese);
      background: rgba(244, 168, 0, .1)
    }

    /* ── MESSAGES ── */
    .messages {
      flex: 1;
      overflow-y: auto;
      padding: 1rem 1.1rem;
      display: flex;
      flex-direction: column;
      gap: 11px;
      scroll-behavior: smooth;
      scrollbar-width: thin;
      scrollbar-color: rgba(244, 168, 0, .1) transparent
    }

    .msg-row {
      display: flex;
      gap: 8px;
      animation: fadeUp .28s ease
    }

    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(8px)
      }

      to {
        opacity: 1;
        transform: none
      }
    }

    .msg-row.user {
      flex-direction: row-reverse
    }

    .msg-av {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .95rem;
      margin-top: 2px
    }

    .msg-av.bot {
      background: linear-gradient(135deg, var(--cheese), var(--melt))
    }

    .msg-av.usr {
      background: linear-gradient(135deg, #3b82f6, #8b5cf6);
      font-size: 10px;
      font-weight: 800;
      color: #fff
    }

    .bubble {
      max-width: 70%;
      padding: .68rem .92rem;
      border-radius: 14px;
      font-size: 13.5px;
      line-height: 1.65;
      font-weight: 500;
      word-break: break-word
    }

    .bubble.bot {
      background: var(--bot-bbl);
      border: 1px solid var(--border2);
      color: var(--text);
      border-radius: 4px 14px 14px 14px
    }

    .bubble.usr {
      background: var(--user-bbl);
      color: #fff;
      border-radius: 14px 4px 14px 14px
    }

    .msg-meta {
      font-size: 10px;
      color: var(--muted);
      margin-top: 3px;
      font-weight: 600
    }

    .msg-row.user .msg-meta {
      text-align: right
    }

    /* Typing dots */
    .typing-wrap {
      display: flex;
      gap: 5px;
      padding: .45rem .1rem
    }

    .td {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--cheese);
      animation: tdot .9s ease infinite
    }

    .td:nth-child(2) {
      animation-delay: .18s
    }

    .td:nth-child(3) {
      animation-delay: .36s
    }

    @keyframes tdot {

      0%,
      80%,
      100% {
        transform: scale(.65);
        opacity: .3
      }

      40% {
        transform: scale(1);
        opacity: 1
      }
    }

    /* ── QUICK REPLIES ── */
    .qr-row {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 4px;
      padding-left: 40px
    }

    .qr {
      background: var(--surface2);
      border: 1.5px solid var(--border2);
      border-radius: 18px;
      padding: 5px 13px;
      font-size: 11.5px;
      font-weight: 700;
      color: var(--text);
      cursor: pointer;
      transition: all .2s;
      white-space: nowrap
    }

    .qr:hover {
      background: var(--cheese);
      border-color: var(--cheese);
      color: #1A0A00;
      transform: translateY(-1px)
    }

    /* ── WIDGETS ── */
    .w-card {
      background: var(--surface3);
      border: 1.5px solid var(--border2);
      border-radius: 12px;
      padding: .82rem;
      margin-top: .5rem
    }

    .w-title {
      font-family: 'Fredoka One', cursive;
      font-size: .9rem;
      color: var(--cheese);
      margin-bottom: .5rem;
      display: flex;
      align-items: center;
      gap: 6px
    }

    .w-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 12px;
      font-weight: 600;
      color: var(--muted);
      padding: 3px 0;
      gap: 8px
    }

    .w-row .v {
      color: var(--text)
    }

    .w-div {
      height: 1px;
      background: var(--border);
      margin: .4rem 0
    }

    .w-total {
      display: flex;
      justify-content: space-between;
      font-size: 14px;
      font-weight: 800;
      color: var(--text);
      padding-top: .38rem;
      border-top: 1.5px solid var(--border2)
    }

    .w-total span:last-child {
      font-family: 'Fredoka One', cursive;
      color: var(--cheese-dark)
    }

    /* Menu grid */
    .mgrid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 7px;
      margin-top: .45rem
    }

    .mi {
      background: var(--surface2);
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: .6rem;
      cursor: pointer;
      transition: all .2s;
      text-align: center
    }

    .mi:hover {
      border-color: var(--cheese);
      background: rgba(244, 168, 0, .05);
      transform: translateY(-2px)
    }

    .mi-em {
      font-size: 1.6rem;
      display: block;
      margin-bottom: 3px
    }

    .mi-name {
      font-size: 11px;
      font-weight: 800;
      color: var(--text);
      line-height: 1.3
    }

    .mi-desc {
      font-size: 9px;
      color: var(--muted);
      font-weight: 500;
      margin-top: 2px;
      line-height: 1.3
    }

    .mi-price {
      font-family: 'Fredoka One', cursive;
      font-size: .87rem;
      color: var(--cheese-dark);
      margin-top: 3px
    }

    .mi-add {
      background: var(--cheese);
      border: none;
      border-radius: 6px;
      padding: 3px 10px;
      font-size: 10px;
      font-weight: 800;
      color: #1A0A00;
      cursor: pointer;
      font-family: 'Nunito', sans-serif;
      margin-top: 5px;
      transition: all .2s
    }

    .mi-add:hover {
      background: var(--melt);
      color: #fff
    }

    /* Cart items */
    .ci-row {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 5px 0;
      border-bottom: 1px solid var(--border)
    }

    .ci-row:last-child {
      border-bottom: none
    }

    .ci-em {
      font-size: 1.15rem;
      width: 26px;
      text-align: center;
      flex-shrink: 0
    }

    .ci-name {
      flex: 1;
      font-size: 12px;
      font-weight: 700;
      color: var(--text)
    }

    .ci-ctrl {
      display: flex;
      align-items: center;
      gap: 4px;
      flex-shrink: 0
    }

    .cb {
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 5px;
      width: 21px;
      height: 21px;
      cursor: pointer;
      font-size: .85rem;
      font-weight: 800;
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all .2s
    }

    .cb:hover {
      background: var(--cheese);
      color: #1A0A00;
      border-color: var(--cheese)
    }

    .ci-q {
      font-size: 12px;
      font-weight: 800;
      min-width: 15px;
      text-align: center;
      color: var(--text)
    }

    .ci-price {
      font-family: 'Fredoka One', cursive;
      font-size: .82rem;
      color: var(--cheese-dark);
      min-width: 50px;
      text-align: right
    }

    .ci-del {
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      font-size: .82rem;
      transition: color .2s;
      padding: 2px
    }

    .ci-del:hover {
      color: var(--red)
    }

    /* Order form */
    .o-form {
      background: var(--surface3);
      border: 1.5px solid var(--border2);
      border-radius: 12px;
      padding: .85rem;
      margin-top: .45rem
    }

    .of-title {
      font-family: 'Fredoka One', cursive;
      font-size: .9rem;
      color: var(--cheese);
      margin-bottom: .7rem
    }

    .of-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 7px;
      margin-bottom: .6rem
    }

    .of-f {
      margin-bottom: .55rem
    }

    .of-lbl {
      font-size: 10px;
      font-weight: 800;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-bottom: 3px
    }

    .of-inp {
      width: 100%;
      background: var(--surface2);
      border: 1.5px solid var(--border);
      border-radius: 8px;
      padding: 8px 10px;
      font-size: 12.5px;
      font-family: 'Nunito', sans-serif;
      color: var(--text);
      outline: none;
      transition: all .2s
    }

    .of-inp:focus {
      border-color: var(--cheese);
      box-shadow: 0 0 0 2px rgba(244, 168, 0, .08)
    }

    .of-inp::placeholder {
      color: var(--muted);
      opacity: .6
    }

    .of-inp.err {
      border-color: var(--red)
    }

    .pay-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 6px;
      margin-top: .25rem
    }

    .pay-opt {
      border: 1.5px solid var(--border);
      border-radius: 9px;
      padding: 8px 6px;
      cursor: pointer;
      transition: all .2s;
      text-align: center;
      background: var(--surface2)
    }

    .pay-opt:hover,
    .pay-opt.sel {
      border-color: var(--cheese);
      background: rgba(244, 168, 0, .09)
    }

    .po-ico {
      font-size: 1.2rem
    }

    .po-nm {
      font-size: 10.5px;
      font-weight: 700;
      color: var(--text);
      margin-top: 2px
    }

    .promo-row {
      display: flex;
      gap: 6px;
      margin-bottom: .55rem
    }

    .promo-inp {
      flex: 1;
      background: var(--surface2);
      border: 1.5px solid var(--border);
      border-radius: 7px;
      padding: 7px 10px;
      font-size: 12px;
      font-family: 'Nunito', sans-serif;
      color: var(--text);
      outline: none;
      transition: border-color .2s;
      text-transform: uppercase
    }

    .promo-inp:focus {
      border-color: var(--cheese)
    }

    .promo-inp::placeholder {
      text-transform: none
    }

    .promo-apply {
      background: var(--surface3);
      border: 1.5px solid var(--border2);
      border-radius: 7px;
      padding: 7px 12px;
      font-size: 11px;
      font-weight: 800;
      color: var(--cheese);
      cursor: pointer;
      font-family: 'Nunito', sans-serif;
      white-space: nowrap;
      transition: all .2s
    }

    .promo-apply:hover {
      background: var(--cheese);
      color: #1A0A00
    }

    .promo-msg {
      font-size: 11px;
      font-weight: 700;
      margin-bottom: .4rem;
      display: none
    }

    .of-summary {
      background: var(--surface2);
      border-radius: 8px;
      padding: .55rem .75rem;
      margin: .45rem 0;
      font-size: 12px;
      font-weight: 700;
      color: var(--text);
      display: flex;
      justify-content: space-between
    }

    .submit-btn {
      width: 100%;
      background: linear-gradient(135deg, var(--cheese-dark), var(--melt));
      color: #fff;
      border: none;
      border-radius: 9px;
      padding: 10px;
      font-size: 13px;
      font-weight: 800;
      cursor: pointer;
      font-family: 'Nunito', sans-serif;
      transition: all .2s;
      margin-top: .3rem
    }

    .submit-btn:hover {
      filter: brightness(1.08);
      transform: translateY(-1px)
    }

    .submit-btn:disabled {
      opacity: .4;
      cursor: not-allowed;
      transform: none
    }

    /* Confirmation */
    .conf-card {
      background: linear-gradient(135deg, rgba(34, 197, 94, .08), rgba(34, 197, 94, .04));
      border: 1.5px solid var(--green-bd);
      border-radius: 12px;
      padding: .85rem;
      margin-top: .45rem;
      animation: confIn .5s cubic-bezier(.175, .885, .32, 1.275)
    }

    @keyframes confIn {
      from {
        transform: scale(.9);
        opacity: 0
      }

      to {
        transform: scale(1);
        opacity: 1
      }
    }

    .cc-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: var(--green-bg);
      border: 1px solid var(--green-bd);
      border-radius: 20px;
      padding: 3px 10px;
      font-size: 10px;
      font-weight: 800;
      color: var(--green);
      margin-bottom: .55rem
    }

    .cc-id {
      font-family: 'Fredoka One', cursive;
      font-size: 1.25rem;
      color: var(--green);
      display: block;
      margin: .2rem 0
    }

    .prog-track {
      height: 6px;
      background: var(--surface2);
      border-radius: 6px;
      margin: .5rem 0;
      overflow: hidden
    }

    .prog-bar {
      height: 100%;
      background: linear-gradient(90deg, var(--green), var(--cheese));
      border-radius: 6px;
      width: 15%;
      animation: pAnim 2s ease forwards
    }

    @keyframes pAnim {
      to {
        width: 28%
      }
    }

    .steps-row {
      display: flex;
      justify-content: space-between;
      margin-top: .35rem
    }

    .step-item {
      text-align: center;
      flex: 1
    }

    .step-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      margin: 0 auto 3px;
      transition: background .3s
    }

    .step-dot.done {
      background: var(--green)
    }

    .step-dot.active {
      background: var(--cheese);
      animation: blink 1.4s infinite
    }

    .step-dot.pend {
      background: var(--surface2);
      border: 1.5px solid var(--border2)
    }

    .step-lbl {
      font-size: 9px;
      font-weight: 700;
      color: var(--muted)
    }

    /* Deals */
    .deal-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 5px 0;
      border-bottom: 1px solid var(--border);
      cursor: pointer;
      transition: background .15s;
      border-radius: 5px
    }

    .deal-item:last-child {
      border-bottom: none
    }

    .deal-item:hover {
      background: rgba(244, 168, 0, .04)
    }

    .di-em {
      font-size: 1.2rem;
      width: 28px;
      text-align: center;
      flex-shrink: 0
    }

    .di-info {
      flex: 1
    }

    .di-name {
      font-size: 12px;
      font-weight: 700;
      color: var(--text)
    }

    .di-badge {
      font-size: 9px;
      font-weight: 800;
      background: rgba(255, 107, 0, .14);
      color: var(--melt);
      border-radius: 5px;
      padding: 1px 5px;
      display: inline-block;
      margin-top: 1px
    }

    .di-price {
      text-align: right;
      flex-shrink: 0
    }

    .di-now {
      font-family: 'Fredoka One', cursive;
      font-size: .88rem;
      color: var(--cheese-dark);
      display: block
    }

    .di-old {
      font-size: 10px;
      color: var(--muted);
      text-decoration: line-through
    }

    /* Track widget */
    .track-card {
      background: var(--surface3);
      border: 1.5px solid var(--border2);
      border-radius: 12px;
      padding: .82rem;
      margin-top: .45rem
    }

    .ts {
      display: flex;
      gap: 9px;
      align-items: flex-start;
      padding: .3rem 0
    }

    .ts-l {
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 18px;
      flex-shrink: 0
    }

    .ts-dot {
      width: 11px;
      height: 11px;
      border-radius: 50%;
      flex-shrink: 0;
      border: 2px solid var(--border2)
    }

    .ts-dot.done {
      background: var(--green);
      border-color: var(--green)
    }

    .ts-dot.active {
      background: var(--cheese);
      border-color: var(--cheese);
      animation: blink 1.4s infinite
    }

    .ts-dot.pend {
      background: var(--surface2)
    }

    .ts-line {
      width: 2px;
      flex: 1;
      background: var(--border);
      margin: 2px 0;
      min-height: 12px
    }

    .ts-info {
      flex: 1
    }

    .ts-lbl {
      font-size: 12px;
      font-weight: 700;
      color: var(--text)
    }

    .ts-sub {
      font-size: 10px;
      color: var(--muted);
      font-weight: 600;
      margin-top: 1px
    }

    /* Float cart */
    .float-cart {
      position: absolute;
      bottom: 80px;
      right: 12px;
      background: var(--cheese);
      border-radius: 14px;
      padding: .5rem .95rem;
      display: flex;
      align-items: center;
      gap: 7px;
      cursor: pointer;
      box-shadow: 0 4px 18px rgba(244, 168, 0, .4);
      z-index: 100;
      font-family: 'Fredoka One', cursive;
      font-size: .82rem;
      color: #1A0A00;
      opacity: 0;
      transform: translateY(8px);
      pointer-events: none;
      transition: all .3s
    }

    .float-cart.show {
      opacity: 1;
      transform: translateY(0);
      pointer-events: all
    }

    .fc-ct {
      background: #1A0A00;
      color: var(--cheese);
      border-radius: 50%;
      width: 19px;
      height: 19px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      font-weight: 800
    }

    /* ── INPUT BAR ── */
    .input-bar {
      background: var(--surface);
      border-top: 1px solid var(--border);
      padding: .75rem 1rem;
      flex-shrink: 0
    }

    .inp-row {
      display: flex;
      gap: 7px;
      align-items: flex-end
    }

    .voice-btn {
      background: var(--surface2);
      border: 1.5px solid var(--border2);
      border-radius: 10px;
      width: 40px;
      height: 40px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      transition: all .2s;
      flex-shrink: 0;
      color: var(--muted)
    }

    .voice-btn:hover {
      background: rgba(244, 168, 0, .1);
      color: var(--cheese)
    }

    .voice-btn.rec {
      background: rgba(239, 68, 68, .1);
      border-color: rgba(239, 68, 68, .3);
      color: var(--red);
      animation: recPulse .8s ease infinite
    }

    @keyframes recPulse {
      50% {
        transform: scale(1.07)
      }
    }

    .inp-wrap {
      flex: 1;
      background: var(--surface2);
      border: 1.5px solid var(--border);
      border-radius: 11px;
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 0 10px;
      transition: border-color .2s
    }

    .inp-wrap:focus-within {
      border-color: var(--cheese)
    }

    #msgInput {
      flex: 1;
      background: none;
      border: none;
      padding: 10px 0;
      font-size: 13px;
      font-family: 'Nunito', sans-serif;
      color: var(--text);
      outline: none;
      resize: none;
      max-height: 88px;
      line-height: 1.5
    }

    #msgInput::placeholder {
      color: var(--muted);
      opacity: .62
    }

    .send-btn {
      background: var(--cheese);
      border: none;
      border-radius: 8px;
      width: 33px;
      height: 33px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .9rem;
      transition: all .2s;
      flex-shrink: 0;
      color: #1A0A00
    }

    .send-btn:hover {
      background: var(--melt);
      color: #fff;
      transform: scale(1.06)
    }

    .send-btn:disabled {
      opacity: .32;
      cursor: not-allowed;
      transform: none
    }

    .inp-hint {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: .3rem
    }

    .voice-hint {
      font-size: 10px;
      color: var(--muted);
      font-weight: 600
    }

    .voice-hint.rec {
      color: var(--red)
    }

    .tts-toggle {
      font-size: 10px;
      font-weight: 800;
      color: var(--muted);
      cursor: pointer;
      background: none;
      border: none;
      font-family: 'Nunito', sans-serif;
      transition: color .2s;
      padding: 0
    }

    .tts-toggle:hover,
    .tts-toggle.on {
      color: var(--cheese)
    }

    /* Toast */
    .toast {
      position: fixed;
      bottom: 88px;
      left: 50%;
      transform: translateX(-50%) translateY(50px);
      background: var(--surface3);
      color: var(--text);
      padding: 7px 16px;
      border-radius: 24px;
      font-size: 12px;
      font-weight: 700;
      z-index: 999;
      transition: all .3s;
      opacity: 0;
      border: 1px solid var(--border2);
      pointer-events: none;
      white-space: nowrap;
      max-width: 90vw;
      text-align: center
    }

    .toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(0)
    }

    /* Mobile */
    .mob-sb {
      display: none;
      position: fixed;
      bottom: 82px;
      left: 12px;
      z-index: 500;
      background: var(--cheese);
      border: none;
      border-radius: 50%;
      width: 42px;
      height: 42px;
      cursor: pointer;
      font-size: 1.1rem;
      box-shadow: 0 4px 14px rgba(244, 168, 0, .4);
      align-items: center;
      justify-content: center;
      transition: all .2s
    }

    @media(max-width:800px) {
      .sidebar {
        position: fixed;
        left: -268px;
        top: 0;
        bottom: 0;
        z-index: 400
      }

      .sidebar.open {
        left: 0
      }

      .mob-sb {
        display: flex
      }

      .bubble {
        max-width: 85%
      }

      .mgrid {
        grid-template-columns: 1fr 1fr
      }
    }

    @media(max-width:460px) {
      .mgrid {
        grid-template-columns: 1fr
      }

      .of-grid {
        grid-template-columns: 1fr
      }
    }
  </style>
</head>

<body>
  <div class="shell">

    <!-- ═══════════ SIDEBAR ═══════════ -->
    <aside class="sidebar" id="sidebar">
      <div class="sb-top">
        <div class="brand">🧀 Cheesy Burgers</div>
        <div class="brand-sub">AI Ordering Agent</div>
        <div class="live-pill">
          <div class="ldot"></div><span class="live-txt">CheeseBot Online • 24/7</span>
        </div>
      </div>

      <div class="sb-user">
        <div class="sb-av"><?= htmlspecialchars($userInitials) ?></div>
        <div>
          <div class="sb-uname"><?= htmlspecialchars($userName) ?></div>
          <div class="sb-utag"><?= $isLoggedIn ? ($isAdmin ? '⚙️ Admin' : '⭐ Gold Member') : '👤 Guest' ?></div>
        </div>
      </div>

      <div class="sb-scroll">
        <div class="sb-lbl">Quick Actions</div>
        <button class="qb" onclick="sq('Mujhe pura menu dikhao')"><span class="i">📋</span>Full Menu</button>
        <button class="qb" onclick="sq('Burger menu dikhao')"><span class="i">🍔</span>Burgers</button>
        <button class="qb" onclick="sq('Pizza menu dikhao')"><span class="i">🍕</span>Pizzas</button>
        <button class="qb" onclick="sq('Fries aur sides dikhao')"><span class="i">🍟</span>Sides & Fries</button>
        <button class="qb" onclick="sq('Drinks dikhao')"><span class="i">🥤</span>Drinks</button>
        <button class="qb" onclick="sq('Desserts dikhao')"><span class="i">🍰</span>Desserts</button>
        <button class="qb" onclick="sq('Aaj ke hot deals kya hain?')"><span class="i">🔥</span>Hot Deals <span
            class="badge">6</span></button>
        <button class="qb" onclick="sq('Mera cart dikhao')"><span class="i">🛒</span>My Cart <span class="badge"
            id="sbCart"><?= $cartCount ?: '0' ?></span></button>
        <button class="qb" onclick="sq('Order karna hai')"><span class="i">✅</span>Place Order</button>
        <button class="qb" onclick="sq('Mera order track karna hai')"><span class="i">📍</span>Track Order</button>
        <button class="qb" onclick="sq('Promo code apply karna hai')"><span class="i">🎁</span>Promo Code</button>

        <!-- Live menu from DB -->
        <div class="sb-lbl">Today's Menu</div>
        <?php
        $preview_cats = ['burger' => '🍔 Burgers', 'pizza' => '🍕 Pizzas', 'fries' => '🍟 Sides'];
        foreach ($preview_cats as $cat => $label):
          if (!empty($menu_by_cat[$cat])):
            ?>
            <div class="mini-cat">
              <div class="mc-h"><?= $label ?></div>
              <?php foreach (array_slice($menu_by_cat[$cat], 0, 3) as $mi): ?>
                <div class="mc-r" onclick="sq('<?= addslashes($mi['name']) ?> add karo 1')">
                  <span><?= $mi['emoji'] . ' ' . htmlspecialchars($mi['name']) ?></span>
                  <span>Rs.<?= number_format($mi['price']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; endforeach; ?>

        <div class="sb-lbl">Promo Codes</div>
        <div class="promo-box">
          <div class="promo-codes">
            CHEESE10 → 10% OFF<br>
            NEWUSER → 15% OFF<br>
            WELCOME20 → 20% OFF
          </div>
        </div>

        <?php if (!empty($user_orders)): ?>
          <div class="sb-lbl">Your Orders</div>
          <?php foreach ($user_orders as $uo):
            $items_arr = json_decode($uo['items'], true);
            $first_item = $items_arr[0]['name'] ?? 'Order';
            $st_class = ['pending' => 'st-p', 'cooking' => 'st-c', 'out' => 'st-o', 'delivered' => 'st-d'][$uo['status']] ?? 'st-p';
            ?>
            <div class="hist-card" onclick="sq('Order #<?= $uo['id'] ?> track karo')">
              <div class="hc-id">#<?= $uo['id'] ?></div>
              <div class="hc-info"><?= htmlspecialchars($first_item) ?> — Rs.<?= number_format($uo['total']) ?></div>
              <span class="hc-st <?= $st_class ?>"><?= $uo['status'] ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="sb-bottom">
        <button class="dm-row" onclick="toggleDark()">
          <span id="dmIco" style="font-size:.9rem">☀️</span>
          <span class="dm-lbl" id="dmLbl">Light Mode</span>
          <div class="dm-pill"></div>
        </button>
        <a href="index.php" class="sb-lnk"><span style="font-size:.9rem">🌐</span>Main Website</a>
        <?php if ($isAdmin): ?>
          <a href="admin.php" class="sb-lnk"><span style="font-size:.9rem">⚙️</span>Admin Panel</a>
        <?php endif; ?>
        <button class="sb-lnk" onclick="clearChat()"><span style="font-size:.9rem">🗑️</span>Clear Chat</button>
        <?php if ($isLoggedIn): ?>
          <a href="auth.php" class="sb-lnk"
            onclick="fetch('auth.php',{method:'POST',body:new URLSearchParams({action:'logout'})})"><span
              style="font-size:.9rem">🚪</span>Logout</a>
        <?php else: ?>
          <a href="index.php" class="sb-lnk"><span style="font-size:.9rem">🔑</span>Login / Sign Up</a>
        <?php endif; ?>
      </div>
    </aside>

    <!-- ═══════════ CHAT ═══════════ -->
    <div class="chat-area">
      <div class="topbar">
        <div class="tb-l">
          <div class="bot-av">🧀</div>
          <div>
            <div class="bot-name">CheeseBot — AI Ordering Agent</div>
            <div class="bot-sub" id="botSub">● Online — Gemini 2.5 Flash · English</div>
          </div>
        </div>
        <div class="tb-r">
          <a href="index.php" class="back-btn">← Menu</a>
          <button class="ib" id="ttsBtn" onclick="toggleTTS()">🔇 Mute</button>
          <button class="ib" onclick="sq('Mera cart dikhao')">🛒 <span id="cartCount"><?= $cartCount ?></span></button>
        </div>
      </div>

      <div class="messages" id="messages"></div>

      <div class="float-cart" id="floatCart" onclick="sq('Mera cart dikhao')">
        🧀 Cart <span id="fcTotal">Rs.0</span>
        <div class="fc-ct" id="fcCt">0</div>
      </div>

      <div class="input-bar">
        <div class="inp-row">
          <button class="voice-btn" id="voiceBtn" onclick="toggleVoice()" title="Voice Input">🎤</button>
          <button class="voice-btn" id="callBtn" onclick="openCallModal()" title="Live Voice Call"
            style="background:rgba(34,197,94,.15);border-color:rgba(34,197,94,.4);color:#22c55e">📞</button>
          <div class="inp-wrap">
            <textarea id="msgInput" placeholder="Type your order here… e.g. 'Show me burgers' or 'I want to order'"
              rows="1" onkeydown="handleKey(event)" oninput="autoGrow(this)"></textarea>
            <button class="send-btn" id="sendBtn" onclick="sendMsg()">➤</button>
          </div>
        </div>
        <div class="inp-hint">
          <span class="voice-hint" id="vHint">🎤 Press mic button and speak in English — or 📞 Call Agent for live voice
            chat</span>
          <button class="tts-toggle" id="ttsTgl" onclick="toggleTTS()">🔊 Bot Awaaz</button>
        </div>
      </div>
    </div>
  </div>

  <button class="mob-sb" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
  <div class="toast" id="toast"></div>

  <script>
    /* ================================================================
       LIVE MENU FROM PHP/DATABASE
    ================================================================ */
    const DB_MENU = <?= $menu_json ?>;
    const USER_ORDERS = <?= $orders_json ?>;
    const LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
    const USER_NAME = <?= json_encode($userName) ?>;
    const USER_INITIALS = <?= json_encode($userInitials) ?>;

    // Build structured menu from DB records
    const MENU = { burger: [], pizza: [], fries: [], wrap: [], dessert: [], drink: [] };
    DB_MENU.forEach(item => {
      const cat = item.cat || 'burger';
      if (!MENU[cat]) MENU[cat] = [];
      MENU[cat].push({ id: parseInt(item.id), name: item.name, e: item.emoji, price: parseInt(item.price), desc: item.desc || '' });
    });
    function allItems() { return Object.values(MENU).flat(); }
    function itemById(id) { return allItems().find(i => i.id === id); }

    // Deals (top picks from DB — first 6 items marked as deals)
    const DEALS = allItems().slice(0, 6).map((it, i) => ({
      ...it,
      badge: ['20% OFF', 'HOT 🔥', 'BUY 1 GET 1', '25% OFF', 'FAN FAV ⭐', 'NEW'][i % 6],
      savings: [200, 250, it.price, 150, 70, 100][i % 6]
    }));

    const PROMOS = { CHEESE10: 10, NEWUSER: 15, WELCOME20: 20 };

    /* ================================================================
       STATE
    ================================================================ */
    let cart = [];
    let convHistory = [];
    let ttsEnabled = true;
    let isTyping = false;
    let recognition = null, isRecording = false;
    let promoApplied = null;
    let selectedPay = 'cod';

    /* Load cart from PHP session on page load */
    <?php if (!empty($cart)): ?>
        (function () {
          const sessCart = <?= json_encode(array_values($cart)) ?>;
          sessCart.forEach(si => {
            cart.push({ id: si.id, name: si.name, e: si.e, price: si.price, qty: si.qty });
          });
          syncCart();
        })();
    <?php endif; ?>

    /* ================================================================
       CART
    ================================================================ */
    function cartSub() { return cart.reduce((s, i) => s + i.price * i.qty, 0); }
    function delivFee(sub) { return sub >= 1500 ? 0 : 80; }
    function discount(sub) { return promoApplied ? Math.round(sub * promoApplied / 100) : 0; }
    function cartTotal() { const s = cartSub(); return s + delivFee(s) - discount(s); }

    function addToCart(id, qty = 1) {
      const item = itemById(id); if (!item) return;
      const ex = cart.find(c => c.id === id);
      if (ex) ex.qty += qty; else cart.push({ ...item, qty });
      syncCart();
      // Persist to PHP session via cart_action.php
      fetch('cart_action.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'add', id: item.id, name: item.name, emoji: item.e, price: item.price })
      });
      toast(`${item.e} ${item.name} — cart mein add!`);
    }
    function changeQty(id, delta) {
      const ex = cart.find(c => c.id === id); if (!ex) return;
      ex.qty = Math.max(0, ex.qty + delta);
      if (ex.qty === 0) cart = cart.filter(c => c.id !== id);
      fetch('cart_action.php', { method: 'POST', body: new URLSearchParams({ action: 'update', id, delta }) });
      syncCart();
    }
    function removeFromCart(id) {
      cart = cart.filter(c => c.id !== id);
      fetch('cart_action.php', { method: 'POST', body: new URLSearchParams({ action: 'update', id, delta: -99 }) });
      syncCart();
    }
    function syncCart() {
      const count = cart.reduce((s, i) => s + i.qty, 0);
      const total = count ? cartTotal() : 0;
      document.getElementById('sbCart').textContent = count;
      document.getElementById('cartCount').textContent = count;
      document.getElementById('fcCt').textContent = count;
      document.getElementById('fcTotal').textContent = 'Rs.' + total.toLocaleString();
      document.getElementById('floatCart').classList.toggle('show', count > 0);
    }

    /* ================================================================
       SYSTEM PROMPT  (sent to Claude with live DB menu)
    ================================================================ */
    function buildPrompt() {
      const menuStr = Object.entries(MENU).map(([cat, items]) =>
        items.length ? cat.toUpperCase() + ': ' + items.map(i => `${i.name} Rs.${i.price} (id:${i.id})`).join(', ') : ''
      ).filter(Boolean).join('\n');

      const cartStr = cart.length
        ? cart.map(i => `${i.name} x${i.qty} = Rs.${i.price * i.qty}`).join(' | ')
        : 'Empty';
      const sub = cartSub(), fee = delivFee(sub), disc = discount(sub), total = sub + fee - disc;
      const orderHist = USER_ORDERS.length
        ? USER_ORDERS.slice(0, 3).map(o => `#${o.id} (${o.status})`).join(', ')
        : 'None';

      return `You are "CheeseBot" — the AI ordering assistant for Cheesy Burgers restaurant in Rawalpindi, Pakistan.

RESTAURANT INFO:
Name: Cheesy Burgers | Murree Road, Rawalpindi | Phone: 051-1234567 | Hours: 12 PM – 2 AM
Delivery fee: Rs.80 (FREE on orders above Rs.1,500)

LIVE MENU:
${menuStr}

HOT DEALS: ${DEALS.map(d => d.name + ' ' + d.badge).join(', ')}
PROMO CODES: CHEESE10=10%, NEWUSER=15%, WELCOME20=20%

CUSTOMER: ${LOGGED_IN ? USER_NAME : 'Guest'}
CART: ${cartStr}
SUBTOTAL: Rs.${sub} | DELIVERY: ${fee === 0 ? 'FREE' : 'Rs.' + fee} | DISCOUNT: Rs.${disc} | TOTAL: Rs.${total}
PREVIOUS ORDERS: ${orderHist}

LANGUAGE RULES (STRICT):
- ALWAYS respond in ENGLISH ONLY — no Urdu, no Roman Urdu, regardless of what the customer writes.
- Be friendly, warm, and enthusiastic about the food!
- Keep replies to 1-2 short sentences only. Be direct and concise.

ORDER STEPS (ask one at a time):
1. What to order + quantity
2. Customer name | 3. Phone (03XX-XXXXXXX) | 4. Delivery address | 5. Payment method | 6. Special note
Then show a complete order summary.

ACTION TAGS (always include alongside your reply text):
[SHOW_MENU:burger] [SHOW_MENU:pizza] [SHOW_MENU:fries] [SHOW_MENU:wrap] [SHOW_MENU:drink] [SHOW_MENU:dessert] [SHOW_MENU:all]
[ADD_CART:id:qty]
[SHOW_CART]
[SHOW_DEALS]
[SHOW_ORDER_FORM]
[SHOW_TRACK:orderId]
[ORDER_CONFIRMED:name:phone:address:payment:total:orderId]

IMPORTANT: Always include action tags alongside your normal reply. Be excited about cheese! 🧀`;
    }

    /* ================================================================
       GOOGLE GEMINI API (FREE — gemini-2.0-flash model)
       Proxy: api_proxy.php (key safely server-side mein hai)
    ================================================================ */
    async function callClaude(msg) {   // naam same rakha taake baaki code na toote
      convHistory.push({ role: 'user', content: msg });

      // Keep only last 5 messages for speed
      if (convHistory.length > 5) convHistory = convHistory.slice(-5);

      // Build OpenAI-compatible messages array with system prompt
      const messages = [
        { role: 'system', content: buildPrompt() },
        ...convHistory
      ];

      const res = await fetch('api_proxy.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ messages })
      });

      if (!res.ok) {
        const e = await res.json().catch(() => ({}));
        // Remove the user message we just pushed — prevents broken alternation on retry
        convHistory.pop();
        throw new Error(e.error?.message || 'API Error ' + res.status);
      }

      const data = await res.json();
      const reply = data.choices?.[0]?.message?.content || 'Sorry, no response received. Please try again.';
      convHistory.push({ role: 'assistant', content: reply });
      return reply;
    }

    /* ================================================================
       PARSE ACTIONS
    ================================================================ */
    function parseActions(raw) {
      let text = raw; const actions = [];
      const re = /\[([A-Z_]+)(?::([^\]]*))?\]/g; let m;
      while ((m = re.exec(raw)) !== null) {
        actions.push({ type: m[1], args: (m[2] || '').split(':').filter(Boolean) });
        text = text.replace(m[0], '');
      }
      return { text: text.trim(), actions };
    }

    function runActions(actions) {
      actions.forEach(a => {
        const { type: T, args } = a;
        if (T === 'SHOW_MENU') setTimeout(() => attachMenu(args[0] || 'all'), 200);
        else if (T === 'ADD_CART') addToCart(parseInt(args[0]), parseInt(args[1]) || 1);
        else if (T === 'SHOW_CART') setTimeout(attachCartWidget, 200);
        else if (T === 'SHOW_DEALS') setTimeout(attachDealsWidget, 200);
        else if (T === 'SHOW_ORDER_FORM') setTimeout(attachOrderForm, 200);
        else if (T === 'SHOW_TRACK') setTimeout(() => attachTrackWidget(args[0] || ''), 200);
        else if (T === 'ORDER_CONFIRMED') setTimeout(() => attachConfirmCard(args), 200);
      });
    }

    /* ================================================================
       SEND / RECEIVE
    ================================================================ */
    async function sendMsg(forced) {
      const inp = document.getElementById('msgInput');
      const msg = (forced || inp.value).trim();
      if (!msg || isTyping) return;
      if (!forced) { inp.value = ''; inp.style.height = 'auto'; }
      appendUser(msg); showTyping();
      isTyping = true; document.getElementById('sendBtn').disabled = true;
      try {
        const raw = await callClaude(msg);
        hideTyping();
        const { text, actions } = parseActions(raw);
        appendBot(text);
        runActions(actions);
        // Contextual quick replies
        const lc = raw.toLowerCase();
        if (lc.includes('menu') && !actions.find(a => a.type === 'SHOW_MENU'))
          showQR(['🍔 Burgers', '🍕 Pizzas', '🍟 Sides', '🥤 Drinks', '🍰 Desserts']);
        else if (lc.includes('cart') && cart.length)
          showQR(['✅ Order karo', '➕ Aur add karo', '🗑 Cart clear']);
        if (ttsEnabled) speakText(text);
      } catch (err) {
        hideTyping();
        appendBot(`Sorry! Error: ${err.message}. Please try again 🙏`);
      }
      isTyping = false; document.getElementById('sendBtn').disabled = false;
    }
    function sq(msg) { document.getElementById('msgInput').value = msg; sendMsg(); }
    function sendDirect(raw) { const { text, actions } = parseActions(raw); appendBot(text); runActions(actions); }

    /* ================================================================
       RENDERING
    ================================================================ */
    function appendUser(text) {
      const msgs = document.getElementById('messages'), now = nowTime();
      const d = document.createElement('div'); d.className = 'msg-row user';
      d.innerHTML = `<div><div class="bubble usr">${esc(text)}</div><div class="msg-meta">${now}</div></div><div class="msg-av usr">${USER_INITIALS}</div>`;
      msgs.appendChild(d); scrollEnd();
    }
    function appendBot(text) {
      const msgs = document.getElementById('messages'), now = nowTime();
      const d = document.createElement('div'); d.className = 'msg-row';
      d.innerHTML = `<div class="msg-av bot">🧀</div><div><div class="bubble bot">${fmt(text)}</div><div class="msg-meta">CheeseBot • ${now}</div></div>`;
      msgs.appendChild(d); scrollEnd(); return d;
    }
    function showQR(opts) {
      const msgs = document.getElementById('messages'), row = document.createElement('div');
      row.className = 'qr-row';
      opts.forEach(o => { const b = document.createElement('button'); b.className = 'qr'; b.textContent = o; b.onclick = () => { row.remove(); sq(o); }; row.appendChild(b); });
      msgs.appendChild(row); scrollEnd();
    }
    function showTyping() {
      const msgs = document.getElementById('messages'), d = document.createElement('div');
      d.className = 'msg-row'; d.id = 'typingRow';
      d.innerHTML = `<div class="msg-av bot">🧀</div><div class="bubble bot"><div class="typing-wrap"><div class="td"></div><div class="td"></div><div class="td"></div></div></div>`;
      msgs.appendChild(d); scrollEnd();
    }
    function hideTyping() { const e = document.getElementById('typingRow'); if (e) e.remove(); }
    function scrollEnd() { const m = document.getElementById('messages'); m.scrollTop = m.scrollHeight; }
    function lastBotBubble() {
      const all = document.getElementById('messages').querySelectorAll('.msg-row:not(.user)');
      return all.length ? all[all.length - 1].querySelector('.bubble') : null;
    }
    function attachToBot(el) { const b = lastBotBubble(); if (b) b.appendChild(el); scrollEnd(); }
    function nowTime() { return new Date().toLocaleTimeString('en-PK', { hour: '2-digit', minute: '2-digit' }); }
    function esc(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function fmt(t) { return esc(t).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\*(.+?)\*/g, '<em>$1</em>').replace(/\n/g, '<br>'); }

    /* ================================================================
       WIDGET: MENU
    ================================================================ */
    function attachMenu(cat) {
      let items = cat === 'all' ? allItems() : (MENU[cat] || allItems());
      const w = document.createElement('div'); w.className = 'mgrid'; w.style.marginTop = '.45rem';
      items.forEach(it => {
        const d = document.createElement('div'); d.className = 'mi';
        d.innerHTML = `<span class="mi-em">${it.e}</span><div class="mi-name">${it.name}</div><div class="mi-desc">${it.desc || ''}</div><div class="mi-price">Rs.${it.price}</div><button class="mi-add" onclick="addToCart(${it.id},1);this.textContent='✓ Added!';setTimeout(()=>this.textContent='+ Add',1400)">+ Add</button>`;
        w.appendChild(d);
      });
      attachToBot(w);
    }

    /* ================================================================
       WIDGET: DEALS
    ================================================================ */
    function attachDealsWidget() {
      const w = document.createElement('div'); w.className = 'w-card';
      w.innerHTML = `<div class="w-title">🔥 Aaj ke Hot Deals</div>`;
      DEALS.forEach(d => {
        const row = document.createElement('div'); row.className = 'deal-item';
        row.innerHTML = `<div class="di-em">${d.e}</div><div class="di-info"><div class="di-name">${d.name}</div><div class="di-badge">${d.badge}</div></div><div class="di-price"><span class="di-now">Rs.${d.price}</span><span class="di-old">Rs.${d.price + d.savings}</span></div>`;
        row.onclick = () => addToCart(d.id, 1);
        w.appendChild(row);
      });
      const hint = document.createElement('div'); hint.style.cssText = 'font-size:10px;color:var(--muted);text-align:center;margin-top:.4rem;font-weight:600'; hint.textContent = 'Item pe tap karo — cart mein jayega!';
      w.appendChild(hint); attachToBot(w);
    }

    /* ================================================================
       WIDGET: CART
    ================================================================ */
    function attachCartWidget() {
      const w = buildCartEl(); attachToBot(w);
    }
    function buildCartEl() {
      const w = document.createElement('div'); w.className = 'w-card';
      const sub = cartSub(), fee = delivFee(sub), disc = discount(sub), total = sub + fee - disc;
      if (!cart.length) {
        w.innerHTML = `<div class="w-title">🛒 Aapka Cart</div><p style="font-size:12px;color:var(--muted)">Cart khali hai! Menu se kuch add karo 🧀</p>`;
        return w;
      }
      let html = `<div class="w-title">🛒 Cart (${cart.reduce((s, i) => s + i.qty, 0)} items)</div>`;
      cart.forEach(it => {
        html += `<div class="ci-row"><div class="ci-em">${it.e}</div><div class="ci-name">${it.name}</div><div class="ci-ctrl"><button class="cb" onclick="changeQty(${it.id},-1);refreshCart(this)">−</button><span class="ci-q">${it.qty}</span><button class="cb" onclick="changeQty(${it.id},1);refreshCart(this)">+</button></div><div class="ci-price">Rs.${(it.price * it.qty).toLocaleString()}</div><button class="ci-del" onclick="removeFromCart(${it.id});refreshCart(this)">🗑</button></div>`;
      });
      html += `<div class="w-div"></div>
    <div class="w-row"><span>Subtotal</span><span class="v">Rs.${sub.toLocaleString()}</span></div>
    <div class="w-row"><span>Delivery</span><span class="v">${fee === 0 ? 'FREE 🎉' : 'Rs.' + fee}</span></div>
    ${disc > 0 ? `<div class="w-row"><span style="color:var(--green)">Promo (${promoApplied}%)</span><span class="v" style="color:var(--green)">-Rs.${disc}</span></div>` : ''}
    <div class="w-total"><span>Total</span><span>Rs.${total.toLocaleString()}</span></div>
    <button style="margin-top:.55rem;width:100%;background:linear-gradient(135deg,var(--cheese-dark),var(--melt));color:#fff;border:none;border-radius:8px;padding:9px;font-size:12px;font-weight:800;cursor:pointer;font-family:'Nunito',sans-serif" onclick="sq('Order karna hai — delivery form dikhao')">✅ Order Karo — Rs.${total.toLocaleString()}</button>`;
      w.innerHTML = html; return w;
    }
    function refreshCart(el) { const old = el.closest('.w-card'); if (old) old.replaceWith(buildCartEl()); }

    /* ================================================================
       WIDGET: ORDER FORM
    ================================================================ */
    function attachOrderForm() {
      const sub = cartSub(), fee = delivFee(sub), disc = discount(sub), total = sub + fee - disc;
      if (!cart.length) { const b = lastBotBubble(); if (b) { const n = document.createElement('p'); n.style.cssText = 'font-size:12px;color:var(--muted);margin-top:.4rem'; n.textContent = 'Cart khali hai! Pehle items add karo.'; b.appendChild(n); } return; }
      const w = document.createElement('div'); w.className = 'o-form';
      w.innerHTML = `
    <div class="of-title">📋 Delivery Details — Rs.${total.toLocaleString()}</div>
    <div class="of-grid">
      <div class="of-f"><div class="of-lbl">Naam *</div><input class="of-inp" id="of-name" placeholder="Ali Khan" value="${LOGGED_IN ? USER_NAME : ''}" autocomplete="name"></div>
      <div class="of-f"><div class="of-lbl">Phone *</div><input class="of-inp" id="of-phone" placeholder="03XX-XXXXXXX" type="tel" autocomplete="tel"></div>
    </div>
    <div class="of-f"><div class="of-lbl">Delivery Address *</div><input class="of-inp" id="of-addr" placeholder="House no., Street, Area, City" autocomplete="street-address"></div>
    <div class="of-f">
      <div class="of-lbl">Payment Method *</div>
      <div class="pay-grid">
        <div class="pay-opt sel" onclick="selPay(this,'cod')"><div class="po-ico">💵</div><div class="po-nm">Cash on Delivery</div></div>
        <div class="pay-opt" onclick="selPay(this,'jazzcash')"><div class="po-ico">📱</div><div class="po-nm">JazzCash</div></div>
        <div class="pay-opt" onclick="selPay(this,'easypaisa')"><div class="po-ico">🟢</div><div class="po-nm">EasyPaisa</div></div>
        <div class="pay-opt" onclick="selPay(this,'card')"><div class="po-ico">💳</div><div class="po-nm">Card / Bank</div></div>
      </div>
    </div>
    <div class="of-f"><div class="of-lbl">Special Instructions</div><input class="of-inp" id="of-note" placeholder="Extra spicy? No onions? Batao!"></div>
    <div class="of-lbl" style="margin-bottom:4px">Promo Code</div>
    <div class="promo-row">
      <input class="promo-inp" id="of-promo" placeholder="e.g. CHEESE10">
      <button class="promo-apply" onclick="applyPromo()">Apply</button>
    </div>
    <div class="promo-msg" id="pMsg"></div>
    <div class="of-summary"><span>💰 Total Amount</span><span id="ofTotal" style="font-family:'Fredoka One',cursive;color:var(--cheese-dark)">Rs.${total.toLocaleString()}</span></div>
    <div style="font-size:10px;color:var(--muted);text-align:center;margin-bottom:.35rem;font-weight:600">Delivery: ${fee === 0 ? 'FREE 🎉' : 'Rs.' + fee} | ETA: ~25–35 min</div>
    <button class="submit-btn" onclick="placeOrder(${total})">✅ Order Confirm Karo!</button>`;
      attachToBot(w);
    }
    function selPay(el, m) { document.querySelectorAll('.pay-opt').forEach(p => p.classList.remove('sel')); el.classList.add('sel'); selectedPay = m; }
    function applyPromo() {
      const code = document.getElementById('of-promo')?.value?.trim().toUpperCase();
      const msg = document.getElementById('pMsg'); if (!msg) return;
      if (PROMOS[code]) {
        promoApplied = PROMOS[code];
        const s = cartSub(), f = delivFee(s), d = Math.round(s * promoApplied / 100), t = s + f - d;
        const el = document.getElementById('ofTotal'); if (el) el.textContent = 'Rs.' + t.toLocaleString();
        msg.style.display = 'block'; msg.style.color = 'var(--green)'; msg.textContent = `✅ ${promoApplied}% OFF lagaya!`;
        syncCart(); toast(`🎉 ${promoApplied}% discount apply!`);
      } else {
        msg.style.display = 'block'; msg.style.color = 'var(--red)'; msg.textContent = '❌ Galat promo code!';
      }
    }
    function placeOrder(preTotal) {
      const name = document.getElementById('of-name')?.value?.trim();
      const phone = document.getElementById('of-phone')?.value?.trim();
      const addr = document.getElementById('of-addr')?.value?.trim();
      const note = document.getElementById('of-note')?.value?.trim();
      if (!name || !phone || !addr) { toast('⚠️ Sab zaroori fields fill karo!'); return; }
      if (!/^0[3][0-9]{9}$/.test(phone.replace(/[-\s]/g, ''))) { toast('⚠️ Phone 11 digits chahiye: 03XX-XXXXXXX'); return; }

      const sub = cartSub(), fee = delivFee(sub), disc = discount(sub), total = sub + fee - disc;
      const orderId = 'CB-' + Date.now().toString().slice(-6);
      const payLabels = { cod: 'Cash on Delivery', jazzcash: 'JazzCash', easypaisa: 'EasyPaisa', card: 'Card' };

      // Save to MySQL via checkout.php POST
      const fd = new FormData();
      fd.append('ajax_order', '1');
      fd.append('order_id', orderId);
      fd.append('name', name);
      fd.append('phone', phone);
      fd.append('address', addr);
      fd.append('note', note || '');
      fd.append('payment', selectedPay);
      fd.append('subtotal', sub);
      fd.append('deliv_fee', fee);
      fd.append('total', total);
      fd.append('items', JSON.stringify(cart.map(i => ({ id: i.id, name: i.name, e: i.e, qty: i.qty, price: i.price }))));

      fetch('checkout.php', { method: 'POST', body: fd })
        .catch(() => { }) // Proceed even if checkout.php doesn't handle ajax yet
        .finally(() => {
          cart = []; promoApplied = null; syncCart();
          fetch('cart_action.php', { method: 'POST', body: new URLSearchParams({ action: 'clear' }) });
          document.querySelectorAll('.submit-btn').forEach(b => { b.disabled = true; b.textContent = '✅ Order Placed!'; });
          const msg = `🎉 Zabardast ${name}! Order confirm ho gaya!\n\nOrder ID: **#${orderId}**\nTotal: **Rs.${total.toLocaleString()}** (${payLabels[selectedPay]})\nETA: **~30 minutes** 🛵\n\nHum **${phone}** pe call karenge! 🧀 [ORDER_CONFIRMED:${name}:${phone}:${addr}:${selectedPay}:${total}:${orderId}]`;
          setTimeout(() => sendDirect(msg), 400);
        });
    }

    /* ================================================================
       WIDGET: CONFIRMATION
    ================================================================ */
    function attachConfirmCard(args) {
      const [name, phone, addr, pay, total, orderId] = args;
      const eta = new Date(Date.now() + 30 * 60000).toLocaleTimeString('en-PK', { hour: '2-digit', minute: '2-digit' });
      const w = document.createElement('div'); w.className = 'conf-card';
      w.innerHTML = `
    <div class="cc-badge">✅ ORDER CONFIRMED</div>
    <span class="cc-id">#${orderId || 'CB-???'}</span>
    <div class="w-row"><span>Customer</span><span class="v">${esc(name)}</span></div>
    <div class="w-row"><span>Phone</span><span class="v">${esc(phone)}</span></div>
    <div class="w-row"><span>Address</span><span class="v">${esc(addr)}</span></div>
    <div class="w-row"><span>Total</span><span class="v" style="color:var(--cheese-dark);font-weight:800">Rs.${parseInt(total).toLocaleString()}</span></div>
    <div class="w-row"><span>ETA</span><span class="v" style="color:var(--green);font-weight:800">${eta} (~30 min)</span></div>
    <div class="prog-track"><div class="prog-bar"></div></div>
    <div class="steps-row">
      <div class="step-item"><div class="step-dot done"></div><div class="step-lbl">Received</div></div>
      <div class="step-item"><div class="step-dot active"></div><div class="step-lbl">Cooking</div></div>
      <div class="step-item"><div class="step-dot pend"></div><div class="step-lbl">Out</div></div>
      <div class="step-item"><div class="step-dot pend"></div><div class="step-lbl">Delivered</div></div>
    </div>
    <div style="font-size:10px;color:var(--muted);text-align:center;margin-top:.5rem;font-weight:600">051-1234567 pe confirm call aayegi</div>`;
      attachToBot(w);
    }

    /* ================================================================
       WIDGET: TRACK
    ================================================================ */
    function attachTrackWidget(orderId) {
      const w = document.createElement('div'); w.className = 'track-card';
      if (orderId) {
        // Fetch real status from DB
        w.innerHTML = `<div class="w-title">📍 Loading #${orderId}…</div>`;
        attachToBot(w);
        fetch(`get_order_status.php?order_id=${encodeURIComponent(orderId)}`)
          .then(r => r.json()).then(data => {
            if (data.error) { w.innerHTML = `<div class="w-title">📍 Track Order</div><p style="font-size:12px;color:var(--muted)">${data.error}</p>`; return; }
            const step = data.step || 1;
            const labels = ['Order Mila ✓', 'Kitchen Mein 👨‍🍳', 'Raste Mein 🛵', 'Deliver Ho Gaya ✅'];
            const subs = ['Restaurant ne receive kiya', 'Khana ban raha hai', 'Rider aa raha hai', 'Mazay karo!'];
            let html = `<div class="w-title">📍 Order #${orderId}</div>`;
            [1, 2, 3, 4].forEach(i => {
              const done = i < step, active = i === step, isLast = i === 4;
              html += `<div class="ts"><div class="ts-l"><div class="ts-dot ${done ? 'done' : active ? 'active' : 'pend'}"></div>${!isLast ? '<div class="ts-line"></div>' : ''}</div><div class="ts-info"><div class="ts-lbl" style="color:${done || active ? 'var(--text)' : 'var(--muted)'}">${labels[i - 1]}</div><div class="ts-sub">${subs[i - 1]}</div></div></div>`;
            });
            if (data.rider_name) html += `<div class="w-row" style="margin-top:.4rem"><span>🛵 Rider</span><span class="v">${data.rider_name} | ${data.rider_phone || ''}</span></div>`;
            html += `<div style="font-size:10px;color:var(--muted);text-align:center;margin-top:.5rem;font-weight:600">Status: ${data.status.toUpperCase()}</div>`;
            w.innerHTML = html; scrollEnd();
          }).catch(() => { w.innerHTML = `<div class="w-title">📍 Track</div><p style="font-size:12px;color:var(--muted)">Order status load nahi hua.</p>`; });
      } else {
        // Show recent orders to pick from
        if (!USER_ORDERS.length) {
          w.innerHTML = `<div class="w-title">📍 Track Order</div><p style="font-size:12px;color:var(--muted)">Koi order nahi mila. Pehle order karo! 🧀</p>`;
        } else {
          let html = `<div class="w-title">📍 Apna Order Select Karo</div>`;
          USER_ORDERS.slice(0, 3).forEach(o => {
            const st = { pending: '🟠', cooking: '🔵', out: '🟣', delivered: '🟢', cancelled: '🔴' }[o.status] || '⚪';
            html += `<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border);cursor:pointer" onclick="sq('Order #${o.id} track karo')"><span style="font-size:12px;font-weight:700">${st} #${o.id}</span><span style="font-size:11px;color:var(--muted)">Rs.${parseInt(o.total).toLocaleString()}</span></div>`;
          });
          w.innerHTML = html;
        }
        attachToBot(w);
      }
    }

    /* ================================================================
       VOICE INPUT & TTS
    ================================================================ */
    function toggleVoice() {
      const btn = document.getElementById('voiceBtn'), hint = document.getElementById('vHint');
      if (!('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) { toast('❌ Chrome mein voice kaam karta hai!'); return; }
      if (isRecording) { recognition?.stop(); return; }
      const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
      recognition = new SR(); recognition.continuous = false; recognition.interimResults = true;
      recognition.lang = 'en-US';
      recognition.onstart = () => { isRecording = true; btn.classList.add('rec'); btn.textContent = '⏹️'; hint.className = 'voice-hint rec'; hint.textContent = '🎤 Listening… (press again to stop)'; };
      recognition.onresult = (e) => {
        let t = ''; for (let i = e.resultIndex; i < e.results.length; i++) t += e.results[i][0].transcript;
        document.getElementById('msgInput').value = t; autoGrow(document.getElementById('msgInput'));
        if (e.results[e.results.length - 1].isFinal) { hint.textContent = `✅ Suna: "${t}"`; setTimeout(() => sendMsg(), 500); }
      };
      recognition.onerror = (e) => { if (e.error === 'language-not-supported') { recognition.lang = 'en-US'; recognition.start(); return; } toast('❌ Voice error: ' + e.error); };
      recognition.onend = () => { isRecording = false; btn.classList.remove('rec'); btn.textContent = '🎤'; hint.className = 'voice-hint'; setTimeout(() => { hint.textContent = '🎤 Press mic to speak in English'; }, 2000); };
      recognition.start();
    }
    function toggleTTS() {
      ttsEnabled = !ttsEnabled;
      const btn = document.getElementById('ttsTgl'), tb = document.getElementById('ttsBtn');
      btn.textContent = ttsEnabled ? '🔊 Bot Voice' : '🔇 Bot Mute'; btn.className = 'tts-toggle' + (ttsEnabled ? ' on' : '');
      tb.textContent = ttsEnabled ? '🔊 Voice On' : '🔇 Mute'; tb.className = 'ib' + (ttsEnabled ? ' on' : '');
      toast(ttsEnabled ? '🔊 Bot voice on!' : '🔇 Bot muted!');
      if (!ttsEnabled) window.speechSynthesis?.cancel();
    }
    function speakText(text) {
      if (!ttsEnabled || !window.speechSynthesis) return;
      window.speechSynthesis.cancel();
      const clean = text.replace(/[🧀🍔🍕🍟🥤🛵✅❌🔥🎉💰📋🎤📍]/g, '').replace(/\*+/g, '').substring(0, 250);
      const u = new SpeechSynthesisUtterance(clean); u.lang = 'en-US'; u.rate = 0.95; u.pitch = 1.05;
      window.speechSynthesis.speak(u);
    }

    /* ================================================================
       MISC
    ================================================================ */
    function handleKey(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); } }
    function autoGrow(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 88) + 'px'; }
    function toggleDark() {
      const dark = document.documentElement.getAttribute('data-theme') === 'dark';
      document.documentElement.setAttribute('data-theme', dark ? 'light' : 'dark');
      localStorage.setItem('cb-theme', dark ? 'light' : 'dark');
      document.getElementById('dmIco').textContent = dark ? '🌙' : '☀️';
      document.getElementById('dmLbl').textContent = dark ? 'Dark Mode' : 'Light Mode';
    }
    function clearChat() { if (!confirm('Clear chat history?')) return; document.getElementById('messages').innerHTML = ''; convHistory = []; showWelcome(); }
    function toast(msg) { const t = document.getElementById('toast'); t.textContent = msg; t.classList.add('show'); clearTimeout(t._t); t._t = setTimeout(() => t.classList.remove('show'), 2600); }

    /* ================================================================
       WELCOME
    ================================================================ */
    function showWelcome() {
      const h = new Date().getHours();
      const greet = h < 12 ? 'Good Morning' : h < 17 ? 'Good Afternoon' : 'Good Evening';
      const u = LOGGED_IN ? ` ${USER_NAME}` : '';
      appendBot(`${greet}${u}! 🧀 I'm **CheeseBot** — your AI ordering assistant at Cheesy Burgers!\n\nToday's **Special:** Cheese Overload Burger **20% OFF** — only Rs.950! 🔥\n\nHere's what I can help you with:\n🍔 Browse menu  •  🛒 Place an order  •  📍 Track your order\n🎁 Promo codes  •  📞 Live voice call supported!\n\nWhat would you like today?`);
      showQR(['🍔 Show Burgers', '🍕 Show Pizzas', '🔥 Hot Deals', '🛒 Place an Order']);
    }

    /* ================================================================
       INIT
    ================================================================ */
    window.addEventListener('load', () => {
      const t = localStorage.getItem('cb-theme');
      if (t === 'light') { document.documentElement.setAttribute('data-theme', 'light'); document.getElementById('dmIco').textContent = '🌙'; document.getElementById('dmLbl').textContent = 'Dark Mode'; }
      
      // ✅ FIX: Show TTS as ON by default
      document.getElementById('ttsTgl').textContent = '🔊 Bot Voice';
      document.getElementById('ttsTgl').className = 'tts-toggle on';
      document.getElementById('ttsBtn').textContent = '🔊 Voice On';
      document.getElementById('ttsBtn').className = 'ib on';
      
      showWelcome();
      syncCart();
    });
    document.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); document.getElementById('msgInput').focus(); } });
  </script>
  <!-- ═══════════ CALL PANEL — Full-Screen Mobile UI ═══════════ -->
  <div id="callPanel">
    <!-- TOP: Live activity ticker -->
    <div class="cp-activity-bar" id="cpActivityBar">
      <span class="cp-act-dot"></span>
      <span class="cp-act-scroll" id="cpActScroll">
        🔥 Cheese Overload Burger 20% OFF — Rs.950 &nbsp;|&nbsp; 🍕 Pizza Margherita Rs.750 &nbsp;|&nbsp; 🎁 Use code CHEESY10 for 10% off &nbsp;|&nbsp; ⏱️ Avg delivery: 25 mins &nbsp;|&nbsp; 🌟 Top pick: Double Smash Burger
      </span>
    </div>
    <div class="cp-inner">
      <div class="cp-label">● LIVE CALL</div>
      <div class="cp-avatar" id="cpAvatar">🧀</div>
      <div class="cp-name">CheeseBot</div>
      <div class="cp-status" id="cpStatus">Connecting…</div>
      <div class="cp-timer" id="cpTimer">00:00</div>
      <!-- Chat scroll area inside call panel -->
      <div class="cp-chat-scroll" id="cpChatScroll">
        <div class="cp-bot-bubble" id="cpBotText">CheeseBot is ready…</div>
        <div class="cp-user-text" id="cpUserText"></div>
      </div>
      <!-- BOTTOM BAR: waveform + buttons -->
      <div class="cp-bottom-bar">
        <!-- Animated waveform -->
        <div class="cp-wave" id="cpWave">
          <div class="cp-bar idle"></div>
          <div class="cp-bar idle"></div>
          <div class="cp-bar idle"></div>
          <div class="cp-bar idle"></div>
          <div class="cp-bar idle"></div>
          <div class="cp-bar idle"></div>
          <div class="cp-bar idle"></div>
          <div class="cp-bar idle"></div>
          <div class="cp-bar idle"></div>
          <div class="cp-bar idle"></div>
          <div class="cp-bar idle"></div>
          <div class="cp-bar idle"></div>
        </div>
        <!-- Controls -->
        <div class="cp-controls">
          <button class="cp-ctrl-btn" id="cpHoldBtn" onclick="toggleHold()" title="Hold">
            <span id="cpHoldIco">⏸️</span>
            <span class="cp-ctrl-lbl">Hold</span>
          </button>
          <button class="cp-end-btn" onclick="endCall()" title="End Call">
            <span>📵</span>
            <span class="cp-ctrl-lbl">End</span>
          </button>
          <button class="cp-ctrl-btn" id="cpMuteBtn" onclick="toggleMute()" title="Mute">
            <span id="cpMuteIco">🎤</span>
            <span class="cp-ctrl-lbl" id="cpMuteLbl">Mute</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    /* ================================================================
       LIVE VOICE CALL — Split screen panel
       FIXED: callRec properly initialized, onresult handler added
    ============================================================= */
    let callActive = false;
    let callRec = null;          // initialized in openCallModal()
    let callSpeaking = false;
    let callTimer = null;
    let callSeconds = 0;
    let callOnHold = false;
    let callMuted = false;

    function openCallModal() {
      if (!('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) {
        toast('❌ Use Chrome for voice call!'); return;
      }

      callActive = true;
      callSeconds = 0;
      callOnHold = false;
      callMuted = false;

      // ✅ FIX: Create a FRESH SpeechRecognition instance every call
      const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
      callRec = new SR();
      callRec.lang = 'en-US';
      callRec.continuous = false;
      callRec.interimResults = true;
      callRec.maxAlternatives = 1;

      // ✅ FIX: onresult was completely missing — this is what hears you
      callRec.onresult = function(e) {
        let t = '';
        for (let i = e.resultIndex; i < e.results.length; i++) {
          t += e.results[i][0].transcript;
        }
        // Show interim transcript live in the call panel
        document.getElementById('cpUserText').textContent = '🗣️ You: ' + t;
        setWaveListening();

        // Only process final result
        if (e.results[e.results.length - 1].isFinal) {
          setCallStatus('⏳ Thinking…');
          callProcessVoice(t);
        }
      };

      // ✅ FIX: onend now properly restarts listening after bot finishes speaking
      callRec.onend = function() {
        if (callActive && !callOnHold && !callMuted) {
          setTimeout(() => {
            if (callActive && !window.speechSynthesis.speaking) {
              callListen();
            }
          }, 300);
        }
      };

      // ✅ FIX: onerror with proper recovery
      callRec.onerror = function(event) {
        console.warn('STT Error:', event.error);
        if (event.error === 'no-speech') {
          // No speech detected — just listen again
          if (callActive && !callOnHold && !callMuted) callListen();
        } else if (event.error === 'network') {
          toast('⚠️ Network error — retrying mic…');
          setTimeout(() => { if (callActive) callListen(); }, 800);
        } else if (event.error === 'not-allowed') {
          toast('❌ Microphone permission denied!');
          endCall();
        }
      };

      // Split the screen
      document.querySelector('.shell').classList.add('call-active');

      // Reset panel UI
      setCallStatus('🟢 Connected');
      document.getElementById('cpBotText').textContent = 'Say hello to get started!';
      document.getElementById('cpUserText').textContent = '';
      document.getElementById('cpAvatar').classList.remove('speaking');
      setWaveIdle();

      // Start timer
      callTimer = setInterval(() => {
        callSeconds++;
        const m = String(Math.floor(callSeconds / 60)).padStart(2, '0');
        const s = String(callSeconds % 60).padStart(2, '0');
        document.getElementById('cpTimer').textContent = m + ':' + s;
      }, 1000);

      // Welcome greeting then start listening
      const greet = 'Hello! Welcome to Cheesy Burgers! I\'m CheeseBot. What would you like to order today?';
      addCallBubble('bot', greet);
      callSpeak(greet, () => { if (callActive) callListen(); });
    }

    function endCall() {
      callActive = false;
      try { callRec?.stop(); } catch(e) {}
      window.speechSynthesis?.cancel();
      clearInterval(callTimer);

      document.querySelector('.shell').classList.remove('call-active');
      setWaveIdle();

      const elapsed = document.getElementById('cpTimer').textContent;
      document.getElementById('cpTimer').textContent = '00:00';
      toast('📵 Call ended · ' + elapsed);
    }

    // ── Add bubble to call panel chat scroll ─────────────────────────
    function addCallBubble(who, text) {
      const scroll = document.getElementById('cpChatScroll');
      const div = document.createElement('div');
      div.className = who === 'bot' ? 'cp-bot-bubble' : 'cp-user-text';
      div.textContent = who === 'user' ? '🗣️ You: ' + text : text;
      scroll.appendChild(div);
      scroll.scrollTop = scroll.scrollHeight;
      // Keep the main status text in sync
      if (who === 'bot') document.getElementById('cpBotText').textContent = text;
      if (who === 'user') document.getElementById('cpUserText').textContent = '🗣️ ' + text;
    }

    // ── Status text ────────────────────────────────────────────────────
    function setCallStatus(msg) {
      document.getElementById('cpStatus').textContent = msg;
    }

    // ── Waveform states ────────────────────────────────────────────────
    function setWaveListening() {
      document.querySelectorAll('.cp-bar').forEach((b, i) => {
        b.className = 'cp-bar listening';
        const h = [8, 14, 22, 30, 36, 30, 22, 14, 22, 30, 14, 8];
        b.style.height = (h[i] || 12) + 'px';
        b.style.animation = `waveAnim ${0.5 + i * 0.08}s ease-in-out infinite alternate`;
      });
    }
    function setWaveSpeaking() {
      document.querySelectorAll('.cp-bar').forEach((b, i) => {
        b.className = 'cp-bar';
        b.style.background = 'var(--cheese)';
        const h = [10, 20, 34, 40, 34, 20, 10, 16, 28, 38, 20, 12];
        b.style.height = (h[i] || 16) + 'px';
        b.style.animation = `waveAnim ${0.4 + i * 0.07}s ease-in-out infinite alternate`;
      });
      document.getElementById('cpAvatar').classList.add('speaking');
    }
    function setWaveIdle() {
      document.querySelectorAll('.cp-bar').forEach(b => {
        b.className = 'cp-bar idle';
        b.style.height = '6px';
        b.style.animation = 'none';
        b.style.background = '';
      });
      document.getElementById('cpAvatar')?.classList.remove('speaking');
    }

    // ── TTS for call panel ─────────────────────────────────────────────
    function callSpeak(text, onDone) {
      if (!callActive) return;
      callSpeaking = true;
      setCallStatus('🔊 Speaking…');
      setWaveSpeaking();
      window.speechSynthesis.cancel();

      const clean = text
        .replace(/[🧀🍔🍕🍟🥤🛵✅❌🔥🎉💰📋🎤📍]/g, '')
        .replace(/\*+/g, '')
        .substring(0, 350);

      const utt = new SpeechSynthesisUtterance(clean);
      utt.lang = 'en-US';
      utt.rate = 1.0;
      utt.pitch = 1.05;

      // Pick best available English voice
      const voices = window.speechSynthesis.getVoices();
      const pick = voices.find(v => v.lang.startsWith('en') && v.name.toLowerCase().includes('google'))
        || voices.find(v => v.lang.startsWith('en'))
        || voices[0];
      if (pick) utt.voice = pick;

      utt.onend = () => {
        callSpeaking = false;
        setWaveIdle();
        setCallStatus('🎤 Listening…');
        if (onDone) onDone();
      };
      utt.onerror = () => {
        callSpeaking = false;
        setWaveIdle();
        if (onDone) onDone();
      };

      window.speechSynthesis.speak(utt);
    }

    // ── STT: start listening ───────────────────────────────────────────
    function callListen() {
      if (!callActive || callOnHold || callMuted || !callRec) return;
      setCallStatus('🎤 Listening…');
      setWaveListening();

      try {
        callRec.start();
      } catch (err) {
        // If already started, recreate
        console.warn('callListen error:', err.message);
        setTimeout(() => {
          if (callActive) {
            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            callRec = new SR();
            callRec.lang = 'en-US';
            callRec.continuous = false;
            callRec.interimResults = true;
            // Re-attach handlers
            callRec.onresult = openCallModal._onresult;
            callListen();
          }
        }, 400);
      }
    }

    // ── Process voice → AI → speak loop ───────────────────────────────
    async function callProcessVoice(userText) {
      if (!callActive || !userText.trim()) {
        if (callActive) callListen();
        return;
      }

      setCallStatus('⏳ Thinking…');
      setWaveIdle();
      addCallBubble('user', userText);

      const bye = ['bye', 'goodbye', 'end call', 'hang up', 'stop', 'exit', 'quit'];
      if (bye.some(w => userText.toLowerCase().includes(w))) {
        callSpeak('Thank you for calling Cheesy Burgers! Have a great day. Goodbye!', endCall);
        return;
      }

      try {
        const raw = await callClaude(userText);
        const { text, actions } = parseActions(raw);
        addCallBubble('bot', text);
        // Mirror to main chat too
        appendUser(userText);
        appendBot(text);
        runActions(actions);
        callSpeak(text, () => { if (callActive) callListen(); });
      } catch (err) {
        console.error('callProcessVoice error:', err);
        setCallStatus('⚠️ Error — retrying…');
        callSpeak('Sorry, I had a little trouble. Could you please repeat that?',
          () => { if (callActive) callListen(); });
      }
    }

    // ── Hold & Mute ────────────────────────────────────────────────────
    function toggleHold() {
      callOnHold = !callOnHold;
      document.getElementById('cpHoldIco').textContent = callOnHold ? '▶️' : '⏸️';
      document.getElementById('cpHoldBtn').classList.toggle('active', callOnHold);
      if (callOnHold) {
        try { callRec?.stop(); } catch(e) {}
        window.speechSynthesis?.cancel();
        setCallStatus('⏸️ On Hold');
        setWaveIdle();
      } else {
        setCallStatus('🟢 Resumed');
        if (callActive) callListen();
      }
    }

    function toggleMute() {
      callMuted = !callMuted;
      document.getElementById('cpMuteIco').textContent = callMuted ? '🔇' : '🎤';
      document.getElementById('cpMuteLbl').textContent = callMuted ? 'Unmute' : 'Mute';
      document.getElementById('cpMuteBtn').classList.toggle('active', callMuted);
      if (callMuted) {
        try { callRec?.stop(); } catch(e) {}
        setCallStatus('🔇 Muted');
        setWaveIdle();
      } else {
        setCallStatus('🟢 Listening');
        if (callActive) callListen();
      }
    }
  </script>


</body>

</html>