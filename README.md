# WORK IN PROGRESS



Requirements: 

1) Monero Node or Monero Remote Node.
2) Monero Wallet RPC daemon.


Instructions:
1) Download, install, and run monerod, let it fully sync (alternatively, point monero-wallet-rpc to a Monero Remote Node).
2) Create a wallet file with monero-wallet-cli (you should use a view-only wallet!).
3) Run monero-wallet-rpc daemon (  i.e: screen /usr/local/bin/monero-wallet-rpc --rpc-bind-ip <ip> --rpc-bind-port <port> --wallet-file test --password <wallet_password> --daemon-address <ip>:<port> --confirm-external-bind  --rpc-login <username>:<password>).
4) Download/Install/Activate in the Monero Payment Gateway in WHMCS.
5) Configure the Wallet RPC Daemon Host, Daemon Port, Username, and Password settings that you used for monero-wallet-rpc.

Troubleshooting:
*) In monero/library.php, change $is_debug = false to $is_debug = true


How it works.

monerod downloads and stores the blockchain.
monero-wallet-rpc connects to monerod to view the blockchain.
monero-wallet-rpc watches for incoming payments.
WHMCS queries monero-wallet-rpc for incoming payments.
When a payment is found and the payment_id matches the one from the invoice with the one sent by the clients monero software, the transaction amount is credited to the WHMCS invoice.  The invoice is marked as paid when the full amount is received. The user is then redirected to view the paid invoice.
