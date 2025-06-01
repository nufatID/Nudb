class Nudb {
  constructor(wsUrl, options = {}) {
    this.wsUrl = wsUrl;
    this.socket = null;
    this.listeners = {};
    this.dataCallbacks = {};
    this.queue = [];
    this.headers = options.headers || {};
    this.localStore = null;
    this.reconnectTimeout = null;
    this.ready = this._initIndexedDB().then(() => this.connect());
  }

  async _initIndexedDB() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open("NudbStore", 1);
      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        db.createObjectStore("data", { keyPath: "path" });
      };
      request.onsuccess = (event) => {
        this.localStore = event.target.result;
        resolve();
      };
      request.onerror = (event) => {
        console.error("❌ IndexedDB error:", event.target.errorCode);
        resolve(); // biarkan tetap lanjut meski IndexedDB gagal
      };
    });
  }

  _setLocal(path, value) {
    if (!this.localStore) return;
    const tx = this.localStore.transaction("data", "readwrite");
    tx.objectStore("data").put({ path, value });
  }

  _getLocal(path) {
    return new Promise((resolve) => {
      if (!this.localStore) return resolve(null);
      const tx = this.localStore.transaction("data", "readonly");
      const req = tx.objectStore("data").get(path);
      req.onsuccess = () => resolve(req.result?.value || null);
      req.onerror = () => resolve(null);
    });
  }

  _getAllLocal() {
    return new Promise((resolve) => {
      if (!this.localStore) return resolve([]);
      const tx = this.localStore.transaction("data", "readonly");
      const req = tx.objectStore("data").getAll();
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => resolve([]);
    });
  }

  _deleteLocal(path) {
    if (!this.localStore) return;
    const tx = this.localStore.transaction("data", "readwrite");
    tx.objectStore("data").delete(path);
  }

  connect() {
    if (this.socket && this.socket.readyState === WebSocket.OPEN) return;

    this.socket = new WebSocket(this.wsUrl);

    this.socket.onopen = async () => {
      console.log("🟢 WebSocket connected");

      while (this.queue.length > 0) {
        const action = this.queue.shift();
        this.socket.send(JSON.stringify(action));
      }

      for (const path of Object.keys(this.listeners)) {
        this.sendMessage({ type: "subscribe", path });
      }
    };

    this.socket.onmessage = async (event) => {
      try {
        const msg = JSON.parse(event.data);
        console.log("📩 Received:", msg);

        if (msg.type === "update") {
          if (this.listeners[msg.path]) {
            this.listeners[msg.path].forEach((cb) => cb(msg.data));
          }
          this._setLocal(msg.path, msg.data);
        }

        if (msg.type === "data" && this.dataCallbacks[msg.path]) {
          this.dataCallbacks[msg.path].forEach((cb) => cb(msg.data));
          delete this.dataCallbacks[msg.path];
        }
      } catch (err) {
        console.error("❌ Error parsing WebSocket message:", err);
      }
    };

    this.socket.onclose = () => {
      console.warn("🔴 WebSocket disconnected. Reconnecting...");
      if (this.reconnectTimeout) clearTimeout(this.reconnectTimeout);
      this.reconnectTimeout = setTimeout(() => this.connect(), 3000);
    };
  }

  isConnected() {
    return this.socket && this.socket.readyState === WebSocket.OPEN;
  }

  _generateId() {
    const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    let str = "";
    for (let i = 0; i < 17; i++) {
      str += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return `${Date.now()}${str}`;
  }

  setHeader(key, value) {
    this.headers[key] = value;
  }

  sendMessage(msg) {
    const fullMsg = { ...msg, headers: this.headers };
    if (this.isConnected()) {
      this.socket.send(JSON.stringify(fullMsg));
    } else {
      this.queue.push(fullMsg);
    }
  }

  subscribe(path) {
    this.sendMessage({ type: "subscribe", path });
  }

  on(path, callback) {
    if (!this.listeners[path]) {
      this.listeners[path] = [];
      this.subscribe(path);
    }
    this.listeners[path].push(callback);
  }

  async get(path, callback) {
    const cached = await this._getLocal(path);
    if (cached !== null) {
      callback(cached); // gunakan data lokal dulu
    }
    if (!this.dataCallbacks[path]) this.dataCallbacks[path] = [];
    this.dataCallbacks[path].push(callback);
    this.sendMessage({ type: "get", path });
  }

  async set(path, data) {
    this._setLocal(path, data);
    this.sendMessage({ type: "set", path, data });
  }

  push(path, data) {
    const id = this._generateId();
    const fullPath = `${path}/${id}`;
    this.set(fullPath, data);
    return id;
  }

  async update(path, data) {
    this._setLocal(path, data);
    this.sendMessage({ type: "update", path, data });
  }

  async delete(path) {
    this._deleteLocal(path);
    this.sendMessage({ type: "delete", path });
  }
}

if (typeof window !== "undefined") {
  window.Nudb = Nudb;
}