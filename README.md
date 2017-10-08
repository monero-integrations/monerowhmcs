# MoneroWHMCS
A WHMCS Payment Gateway for accepting Monero

## Dependancies
This plugin is rather simple but there are a few things that need to be set up before hand.

* A web server! Ideally with the most recent versions of PHP and mysql

* The Monero wallet-cli and Monero wallet-rpc tools found [here](https://getmonero.org/downloads/)

* [WHMCS](https://www.whmcs.com/)
This Monero plugin is an payment gateway for WHMCS

## Step 1: Activating the plugin
* Downloading: First of all, you will need to download the plugin.  If you wish, you can also download the latest source code from GitHub. This can be done with the command `git clone https://github.com/monero-integrations/monerowhmcs.git` or can be downloaded as a zip file from the GitHub web page.


* Put the plugin in the correct directory: You will need to put the folder named `monero` from this repo/unzipped release into the wordpress plugins directory. This can be found at `whmcspath/module/gateways/`

* Activate the plugin from the WordPress admin panel: Once you login to the admin panel in WHMCS, click on "Payment Gateways" under "Settings". Then simply click "Activate" where it says "Monero - Payment Gateway"

## Step 2: Get a monero daemon to connect to

### Option 1: Running a full node yourself

To do this: start the monero daemon on your server and leave it running in the background. This can be accomplished by running `./monerod` inside your monero downloads folder. The first time that you start your node, the monero daemon will download and sync the entire monero blockchain. This can take several hours and is best done on a machine with at least 4GB of ram, an SSD hard drive (with at least 15GB of free space), and a high speed internet connection.

### Option 2: Connecting to a remote node
The easiest way to find a remote node to connect to is to visit [moneroworld.com](https://moneroworld.com/#nodes) and use one of the nodes offered. It is probably easiest to use node.moneroworld.com:18089 which will automatically connect you to a random node.

## Step 3: Setup your  monero wallet-rpc

* Setup a monero wallet using the monero-wallet-cli tool. If you do not know how to do this you can learn about it at [getmonero.org](https://getmonero.org/resources/user-guides/monero-wallet-cli.html)

* Start the Wallet RPC and leave it running in the background. This can be accomplished by running `./monero-wallet-rpc --rpc-bind-port 18082 --rpc-login username:password --log-level 2 --wallet-file /path/walletfile` where "username:password" is the username and password that you want to use, seperated by a colon and  "/path/walletfile" is your actual wallet file. If you wish to use a remote node you can add the `--daemon-address` flag followed by the address of the node. `--daemon-address node.moneroworld.com:18089` for example.



## Info on server authentication
It is reccommended that you specify a username/password with your wallet rpc. This can be done by starting your wallet rpc with `monero-wallet-rpc --rpc-bind-port 18082 --rpc-login username:password --wallet-file /path/walletfile` where "username:password" is the username and password that you want to use, seperated by a colon. Alternatively, you can use the `--restricted-rpc` flag with the wallet rpc like so `./monero-wallet-rpc --testnet --rpc-bind-port 18082 --restricted-rpc --wallet-file wallet/path`.

## Donating Me
XMR Address : `44krVcL6TPkANjpFwS2GWvg1kJhTrN7y9heVeQiDJ3rP8iGbCd5GeA4f3c2NKYHC1R4mCgnW7dsUUUae2m9GiNBGT4T8s2X`
