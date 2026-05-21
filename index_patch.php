<?php
/*
=======================================================================
  index.php MEIN YEH 2 CHANGES KARO:
=======================================================================

CHANGE 1 — Chat FAB button replace karo (line ~584):
OLD CODE:
<button class="chat-fab" onclick="showToast('💬 Live chat coming soon! Call us: 03001234567')">

NEW CODE (neeche wala):
*/
?>

<!-- CHANGE 1: Chat FAB — yahan se copy karo aur index.php mein paste karo -->
<a href="agent.php" class="chat-fab" title="AI Agent se order karo!">
  🧀
  <span class="chat-tooltip">🤖 AI Order Karo!</span>
</a>

<!-- CHANGE 2: Sidebar mein "Help & Support" ke baad yeh add karo -->
<div class="sb-link" onclick="window.location='agent.php'">
  <span class="ico">🤖</span>
  <span class="lbl">AI Agent</span>
  <span class="sb-badge" style="background:var(--green)">New</span>
</div>

<?php
/*
=======================================================================
  XAMPP MEIN KAHAN RAKHNA HAI:
=======================================================================

1. agent.php    →  C:\xampp\htdocs\cheesyburgers\agent.php
2. admin.php    →  C:\xampp\htdocs\cheesyburgers\admin.php   (already hai)
3. index.php    →  C:\xampp\htdocs\cheesyburgers\index.php   (already hai)

Baaki sab files already wahan hain, bas agent.php copy karo.

=======================================================================
  CLAUDE API KEY KAHAN LIKHNI HAI:
=======================================================================

agent.php mein line ~370 ke paas yeh hai:
  const res = await fetch('https://api.anthropic.com/v1/messages', {

Lekin API key frontend mein safe nahi hoti!
Isliye neeche wala api_proxy.php use karo (safe tarika).
*/
?>
