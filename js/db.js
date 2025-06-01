class Nudb {
  constructor(wsUrl, options = {}) {
    this.wsUrl = wsUrl;
    this.socket = null;
    this.listeners = {};
    this.dataCallbacks = {};
    this.queue = [];
    this.headers = options.headers || {}; // ⬅️ Header bisa di-set saat inisialisasi
    this.connect();
  }

  connect() {
    this.socket = new WebSocket(this.wsUrl);

    this.socket.onopen = () => {
      console.log("🟢 WebSocket connected");

      while (this.queue.length > 0) {
        const action = this.queue.shift();
        this.socket.send(JSON.stringify(action));
      }

      Object.keys(this.listeners).forEach((path) => {
        this.sendMessage({ type: "subscribe", path });
      });
    };

    this.socket.onmessage = (event) => {
      try {
        const msg = JSON.parse(event.data);
        console.log("📩 Received message from WebSocket:", msg);

        if (msg.type === "update" && this.listeners[msg.path]) {
          this.listeners[msg.path].forEach((callback) => callback(msg.data));
        }

        if (msg.type === "data" && this.dataCallbacks[msg.path]) {
          this.dataCallbacks[msg.path].forEach((callback) =>
            callback(msg.data)
          );
          delete this.dataCallbacks[msg.path];
        }
      } catch (err) {
        console.error("Error parsing message:", err);
      }
    };

    this.socket.onclose = () => {
      console.warn("🔴 WebSocket disconnected. Reconnecting...");
      setTimeout(() => this.connect(), 3000);
    };
  }
isConnected() {
  return this.socket && this.socket.readyState === WebSocket.OPEN;
}
  _generateId() {
    const chars =
      "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
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
    const fullMsg = {
      ...msg,
      headers: this.headers, // ⬅️ Tambahkan headers di setiap pesan
    };

    if (this.socket.readyState === 1) {
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

  get(path, callback) {
    if (!this.dataCallbacks[path]) {
      this.dataCallbacks[path] = [];
    }
    this.dataCallbacks[path].push(callback);

    this.sendMessage({ type: "get", path });
  }

  set(path, data) {
    this.sendMessage({ type: "set", path, data });
  }

  push(path, data) {
    const id = this._generateId();
    const fullPath = `${path}/${id}`;
    this.set(fullPath, data);
    return id;
  }

  update(path, data) {
    this.sendMessage({ type: "update", path, data });
  }

  delete(path) {
    this.sendMessage({ type: "delete", path });
  }
}

if (typeof window !== "undefined") {
  window.Nudb = Nudb;
}