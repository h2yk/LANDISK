# LANDISK-68B9BD
Unofficial update package builder & sources for [HDL-T](https://www.iodata.jp/product/nas/personal/hdl-t/).

## WARNING

> Your warranty is now void. I am not responsible for bricked devices, dead HDDs, thermonuclear war, or you getting fired because the important files lost. Please do some research if you have any concerns about features included in this package before executing it! YOU are choosing to make these modifications, and if you point the finger at me for messing up your device, I will laugh at you.

## Folders

* Original_Update -> Unpacked original firmware (latest)
* Telnet_installer -> Telnet installer (Using beta flag)

## Requirements

* git
* make
* tar

## How to build?

Simply execute `make`.

## TO-DO

Once you connected,
1. Setup apt-get
2. Apply updates
3. Setup ssh
4. Stop telnetd

Option
1. Edit smb.conf

```
# Supress caps errors
printing = bsd
printcap name = /dev/null

# If you can't login,try commenting out these configs 
#force user = nobody
#force group = nobody
```

## Tip

You have to execute

```sh
mount -o rw,remount /
```

to edit system files