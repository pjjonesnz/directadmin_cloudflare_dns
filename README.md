# DirectAdmin to Cloudflare DNS sync

Script to sync DirectAdmin dns changes *from* DirectAdmin *to* your Cloudflare account


* **Keep your Cloudflare dns records in sync with your server** without having to log in to Cloudflare and manually change them
* **Automatically add new domains** to your Cloudflare account when they are added to your server
* **Set proxy on/off** using a config file **for the entire domain or for individual records**
* Set proxy on/off for all of your domains, using a **default settings file**

Cloudflare has a free dns service which is super fast. It is a great way to move nameserver hosting off your web server and on to a managed platform. And if you want to go even faster, enable their proxy!

This handy script for DirectAdmin syncs the DNS records for your domains FROM DirectAdmin TO your Cloudflare account. Use this script when using Cloudflare as the nameserver host for your domain. 

I use this script in a production environment hosting multiple domains.

## Installation

[Full Installation Instructions Here](./INSTALL.md)

## Usage

1. create/modify your domain in DirectAdmin
2. set your DirectAdmin domain's NS records to customname.ns.cloudflare.com AND customname2.ns.cloudflare.com (where customname/customname2 are the ns record names assigned to you by Cloudflare.
3. Check your Cloudflare account and see the domain settings automatically synchronized with your server's settings.

**NOTE:** If you have already added your domain to Cloudflare and have changed your DNS settings manually there, and they are different from your server, your server will overwrite any settings on Cloudflare and delete any settings that don't exist on your server. The synchronization is one way, *from* your server *to* Cloudflare.

## Customize your proxy settings

Proxy configuration is found in this folder: /usr/local/directadmin/scripts/custom/da_cloudflare_dns_sync/domains

### Proxy defaults

The default settings for your server can be made in: 'default.json'

| Setting | Description | Default |
|---|---|---|
| ```proxy_default``` | the default proxy setting for all valid records (A, CNAME and AAAA) | false |
| ```proxy_record``` | an object that contains individual settings by record type and name | |

Example to enable Cloudflare proxy on ALL DOMAINS, but disable it for ftp and mail A records:

```js
{
    "proxy_default": true,
    "proxy_record": {
        "A": {
          "mail": false,
          "ftp": false
        },
        "CNAME": {
        },
        "AAAA": {
        }
    }
}
```

## Testing your config files

All config files in the domains folder can be tested by running the following command ```/usr/local/directadmin/scripts/custom/dns_write_post.sh test_config```

Any errors while decoding the json file will be displayed.  Any errors in the config options will also be displayed along with the json node tree that contains the error. If you have trouble finding what is causing the error feel free to open an issue.

You can also test that your JSON file format is correct at [JSONLint](https://jsonlint.com/) - although this doesn't check that the config properties are correct. 

Note: Be careful that you don't have any trailing commas in your json file. Remember that json isn't the same as javascript object literal syntax.

## Settings for individual domains

The proxy settings can also be enabled/disabled for particular domains

* Copy the default.json file to **my_full_domain_name**.json (eg. ```cp default.json mydomainname.com.json```)
* Change the settings in the newly create file for your domain as required

Example to enable proxy on your domain record and www but disable it on everything else:

```js
// Example domain name: mydomainname.com
// Filename: mydomainname.com.json

{
    "proxy_default": false,
    "proxy_record": {
        "A": { // Add A records here
          "mydomainname.com.": true,
          "www": true
        },
        "CNAME": { // Add CNAME records here
        },
        "AAAA": { // Add AAAA records here
        }
    }
}
```

**Please note:** comments in the above example must be removed as comments are not valid json.

To cause your domain to update after changing any settings, edit one of the domain records and save your setting (even making it equal to the same setting as it was before will force an update of any changed records).

I hope this script is helpful to you. PLEASE let me know if you have any troubles by creating an issue in Github.
