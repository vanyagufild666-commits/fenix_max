# Trust for the MAX API

`russian-trusted-root-ca.pem` is the public Russian Trusted Root CA certificate issued by The Ministry of Digital Development and Communications. It was exported on 2026-10-05 from this workstation's existing trusted Windows root store, after the official API successfully validated with Node's `--use-system-ca` option.

The PHP client uses this root only for the exact host `platform-api2.max.ru`, as required by [MAX's official API documentation](https://dev.max.ru/docs-api/methods/POST/messages). Peer and hostname verification stay enabled. This file contains a public certificate, not a private key. No global certificate store or browser security setting is changed.
