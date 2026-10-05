CREATE TABLE IF NOT EXISTS leads (
 id TEXT PRIMARY KEY NOT NULL, idempotency TEXT NOT NULL, fingerprint TEXT NOT NULL,
 value TEXT NOT NULL, status TEXT DEFAULT 'new' NOT NULL, created_at TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS leads_idempotency_unique ON leads(idempotency);
CREATE TABLE IF NOT EXISTS outbox (
 id TEXT PRIMARY KEY NOT NULL, lead_id TEXT NOT NULL, status TEXT DEFAULT 'pending' NOT NULL,
 attempts INTEGER DEFAULT 0 NOT NULL, last_error TEXT, updated_at TEXT NOT NULL,
 FOREIGN KEY(lead_id) REFERENCES leads(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_outbox_status_updated ON outbox(status,updated_at);
