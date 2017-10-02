# WORK IN PROGRESS



Requirements: 

1) Monero Node or Monero Remote Node.
2) Monero Wallet RPC daemon.
  i.e: /usr/local/bin/monero-wallet-rpc --rpc-bind-ip <ip> --rpc-bind-port 18999 --wallet-file test --password <wallet_password> --daemon-address <ip>:<port> --confirm-external-bind  --rpc-login <username>:<password>

Instructions:
1) Download/Install/Activate in WHMCS.
2) Configure Wallet RPC Host, Port, Username, and Password settings.

Troubleshooting:
*) In monero/library.php, change $is_debug = false to $is_debug = true
