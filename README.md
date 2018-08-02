Barebones CMS Instant Start
===========================

Quickly install all components of Barebones CMS in just a couple of minutes.  Instant Start is useful for setting up a more permanent installation for anyone just wanting to get started right away, set up a demo/test site without a time limit, and/or play with new plugins/extensions away from a production environment.

Creating a new DigitalOcean Droplet is highly recommended but any Debian-based Linux distribution will probably work fine.  Only use Instant Start on a brand new system.  Failure to do so may result in damage to configuration files and/or data loss.

Features
--------

* A simple set of scripts that automatically install and configure several software products.
* Barebones CMS is ready to use in just a couple of minutes.
* Nearly zero configuration required (see below).
* Has a liberal open source license.  MIT or LGPL, your choice.
* Designed for rapid deployment.
* Sits on GitHub for all of that pull request and issue tracker goodness to easily submit changes and ideas respectively.

Getting Started
---------------

Open the following link in a new tab to start creating a Droplet on DigitalOcean:

[New DigitalOcean Droplet](https://cloud.digitalocean.com/droplets/new?size=s-1vcpu-1gb&distro=ubuntu&options=ipv6)

Under "Select additional options" check the checkbox that says "User data".  Copy and paste the following script into the box that appears:

```sh
#!/bin/sh

export DEBIAN_FRONTEND=noninteractive;

apt -y update;
apt -y dist-upgrade;
apt -y install openssl git curl php-cli;

export PUBLIC_IPV4=$(curl -s http://169.254.169.254/metadata/v1/interfaces/public/0/ipv4/address);

# A list of timezones can be found here:  https://en.wikipedia.org/wiki/List_of_tz_database_time_zones
# Or automatic:  https://geoip.ubuntu.com/lookup
export TZ=;

cd /root/;
git clone https://github.com/cubiclesoft/barebones-cms-instant-start.git;
cd barebones-cms-instant-start;

php install.php;
```

Update the `export TZ=` line with your current timezone.  This will be used to set the timezone of the Droplet and associated software (e.g. PHP) so that dates and times are stored and displayed as expected.  The timezone also affects any cron jobs that are set up.  Leave it blank for `UTC +0000`.

Once the Droplet is live, it is ready to use at the IP address(es) assigned to it.

Web server files are stored in `/var/www`.  The admin interface can be accessed using a URL like `http://[IP address here]/admin-[random-string-here]/`.  Sign in via SSH or SFTP to the server to determine via `/var/www` as to what the admin URL is once the server finishes installing and configuring Barebones CMS.

Installed Software
------------------

* PHP CLI.
* Fully automated system update script.
* Nginx.
* PHP FPM.
* PHP extensions (JSON, PDO sqlite, GD).
* Cloud Storage Server with the Cloud Storage Server /feeds extension.
* Barebones CMS API.
* Barebones CMS administrative interface.
* Barebones CMS frontends.

What Is DigitalOcean?
---------------------

DigitalOcean is primarily for quickly setting up a Internet-facing server.  Web hosting service providers abound but most of those are shared hosts with little control.  A Virtual Private Server (VPS), which is what DigitalOcean provides, resides something between shared hosting and cloud/dedicated hosting.  Droplets are intended to be cheap, short-lived VPS instances that are created and destroyed as needed.  Droplets weren't really ever intended for normal web hosting, but quite a few people use them that way.

Running a VPS (or similar) comes with responsbilities.  The biggest one is making sure the system is secure, which means that the system remains fully patched because it won't be done automatically.  However, the ability to do anything on the system as the `root` user usually far outweighs the extra responsibilities that come with it.

If the intent is to run Barebones CMS long-term, I highly recommend using a [OVH VPS](https://www.ovh.com/world/vps/vps-ssd.xml) instead of DigitalOcean.  They offer a lot more hardware for less cost but slightly less comprehensive technical support.  Barebones CMS has no problem running on a OVH VPS SSD 1 instance.  The script under the Getting Started section can be manually modified to set `PUBLIC_IPV4` and then run as `root`.

More Information
----------------

The PHP installation script aims to be idempotent.  That is, if it is run again intentionally or by accident, it will result in the same output.
