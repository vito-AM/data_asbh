/*  public/js/chat-widget.js  */
(() => {
  /* ====== Configuration ====== */
  const API_ENDPOINT      = "http://127.0.0.1:5001/chat";
  const SIDEBAR_SELECTOR  = "#sidebar";
  const SPACER_PX         = 16;
  const PANEL_BASE_WIDTH  = 384;              // 24 rem
  const STORAGE_KEY       = "asbhChatHistory";

  /* ====== 0. Helpers localStorage ====== */
  let history = [];
  function loadHistory() {
    try { history = JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; } catch { history = []; }
  }
  function saveHistory() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(history));
  }

  /* ====== DOM : Bouton ====== */
  const bubble = document.createElement("button");
  bubble.id = "asbh-chat-bubble";
  bubble.className =
    "fixed bottom-4 w-14 h-14 bg-blue-600 hover:bg-blue-700 text-white " +
    "rounded-full flex items-center justify-center shadow-lg z-[9999] transition";
  bubble.innerHTML = "💬";
  document.body.appendChild(bubble);

  /* ====== DOM : Panel ====== */
  const panel = document.createElement("div");
  panel.className =
    "hidden fixed bottom-24 max-h-[75vh] bg-black/80 backdrop-blur-md " +
    "border border-white/20 rounded-2xl flex flex-col text-white " +
    "shadow-xl z-[9999]";
  panel.style.width = PANEL_BASE_WIDTH + "px";
  panel.innerHTML = `
    <header class="flex items-center justify-between p-3 border-b border-white/10">
      <span class="font-semibold">Chatbot Rugby</span>
      <div class="flex gap-3 items-center">
        <button id="asbh-clear"  title="Vider l’historique" class="text-lg hover:text-yellow-400">🗑</button>
        <button id="asbh-close"  class="text-xl leading-none hover:text-red-400">&times;</button>
      </div>
    </header>

    <main id="asbh-log"
          class="flex-1 overflow-y-auto px-3 py-2 space-y-2 text-sm"></main>

    <footer class="p-3 border-t border-white/10 flex gap-2">
      <input id="asbh-input" type="text"
             placeholder="Pose ta question…"
             class="flex-1 bg-white/10 px-2 py-1 rounded placeholder-gray-300 focus:outline-none" />
      <button id="asbh-send"
              class="px-3 py-1 bg-blue-600 rounded hover:bg-blue-700 transition">Envoyer</button>
    </footer>
  `;
  document.body.appendChild(panel);

  /* ====== Positionnement ====== */
  function positionChat() {
    const sidebarWidth = (document.querySelector(SIDEBAR_SELECTOR)?.getBoundingClientRect().width) || 0;
    const offset       = sidebarWidth + SPACER_PX;
    bubble.style.left  = panel.style.left = `${offset}px`;
    panel.style.width  = window.innerWidth < 640 ? "90%" : PANEL_BASE_WIDTH + "px";
  }
  positionChat();
  window.addEventListener("resize", positionChat);

  /* ====== Fonctions affichage ====== */
  const $log   = panel.querySelector("#asbh-log");
  const $input = panel.querySelector("#asbh-input");

  const print = (html) => {
    $log.insertAdjacentHTML("beforeend", html);
    $log.scrollTop = $log.scrollHeight;
  };

  // reconstituer historique visuel
  loadHistory();
  history.forEach(msg => {
    print(`<p><strong>${msg.role === "user" ? "🧑‍💬" : "🤖"}</strong> ${msg.text}</p>`);
  });

  /* Loader animé “…” */
  function startDots(id) {
    let i = 0;
    return setInterval(() => {
      const el = document.getElementById(id);
      if (el) el.textContent = "⏳ " + ".".repeat((i++ % 3) + 1);
    }, 400);
  }

  /* ====== Envoi ====== */
  async function sendMessage() {
    const message = $input.value.trim();
    if (!message) return;

    // 1. Affiche & log côté user
    print(`<p><strong>🧑‍💬</strong> ${message}</p>`);
    history.push({ role:"user", text:message });
    saveHistory();
    $input.value = "";

    // 2. Loader
    const id = `think-${Date.now()}`; print(`<p id="${id}">⏳ …</p>`);
    const interval = startDots(id);

    try {
      const resp   = await fetch(API_ENDPOINT, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message }),
      });
      const { reply } = await resp.json();

      clearInterval(interval);
      document.getElementById(id).outerHTML =
        `<p><strong>🤖</strong> ${reply}</p>`;

      history.push({ role:"bot", text:reply });
      saveHistory();
    } catch (err) {
      clearInterval(interval);
      document.getElementById(id).outerHTML =
        `<p class="text-red-400">Erreur : ${err}</p>`;
    }
  }

  /* ====== Événements ====== */
  bubble.addEventListener("click", () => {
    panel.classList.toggle("hidden");
    if (!panel.classList.contains("hidden")) $input.focus();
  });
  panel.querySelector("#asbh-close").addEventListener("click", () => panel.classList.add("hidden"));
  panel.querySelector("#asbh-clear").addEventListener("click", () => {
    if (confirm("Effacer l’historique du chat ?")) {
      history = []; saveHistory(); $log.innerHTML = "";
    }
  });
  panel.querySelector("#asbh-send").addEventListener("click", sendMessage);
  $input.addEventListener("keydown", e => e.key === "Enter" && sendMessage());
})();
