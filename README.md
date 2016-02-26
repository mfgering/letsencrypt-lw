# [letsencrypt-lw](https://github.com/mfgering/letsencrypt-lw)
Instructions and code for running *letsencrypt* on a CentOS 6.x virtual private server, e.g. on [LiquidWeb](https://www.liquidweb.com/).

There are a few issues to handle when dealing with a virtual private server running CentOS 6.x:
* *letsencrypt* strongly favors Python 2.7, but CentOS 6.x natively only supports Python 2.6.
* You want to automate installing and renewing certificates, and you don't want to 
shutdown your websites while doing so.

The first issue can be resolved by installing Python 2.7 such that *letsencrypt* will use it instead of the native 2.6 version. And other applications will continue using the native 2.6 version. This is achieved with a python package called virtualenv. To get all this new python stuff, you have to configure CentOS to use a few new repositories.

The second issue can be resolved by using the *webroot* domain verification method of *letsencrypt*.
The *letsencrypt* utility can use different methods to verify a domain, including:
* *manual* -- you interact with it directly, or
* *standalone* -- *letsencrypt* runs its own webserver, requiring you to shutdown 
an already running server or open a new port, or
* *webroot* -- you give *letsencrypt* permission to add and modify files in a 
currently running website
These instructions use the *webroot* method.

# Overview

1. Install the Epel Repository
2. Install the IUS Repository
3. Install Python 2.7 and Git
4. Clone the *letsencrypt* git repository into the root home directory
5. Run letsencrypt-auto to setup the environment local to the root user
6. Initialize directories in each of your web root documents for letsencrypt
7. Run letsencrypt-auto for each cert you need to generate or renew

## Important Notes
* You do steps 1-6 just once. 
* Step 6 is needed to allow *letsencrypt* to run without permission problems (404 errors). Also, if the particular website is running Drupal, you need to create a special .well-known/.htaccess file
* You can automate step 7 in cron jobs and scripts. Since the certificates are good only for 90 days, you need to renew them more frequently, either manually or via cron.

# Details

1. Install the EPEL Repository:

    ``` bash
    yum install epel-release
    ```
    The EPEL (Fedora Extra Packages for Enterprise Linux) repository has useful
    software packages not included in the standard CentOS repositories. In particular,
    we need it for the python *virtualenv* package.

2. Install the IUS Repository:

    ``` bash
    rpm -ivh https://rhel6.iuscommunity.org/ius-release.rpm
    ```
    The IUS (Inline Upstream Stable) repositories have curated, stable packages for Enterprise Linux distributions. We need it for *python 2.7*.

3. Install Python 2.7 and Git:

    ``` bash
    yum --enablerepo=ius install git python27 python27-devel python27-pip python27-setuptools python27-virtualenv -y
    ```

4. Clone the *letsencrypt* git repository into the root home directory:

    ``` bash
    cd /root
    git clone https://github.com/letsencrypt/letsencrypt
    ```
    We will be running *letsencrypt* from the root user home directory. The
    *letsencrypt-auto* program will keep it up-to-date.

5. Run letsencrypt-auto to setup the environment local to the root user:

    ``` bash
    cd /root/letsencrypt
    ./letsencrypt-auto --verbose
    ```
    The above will likely fail with some message like "No installers are available...", 
    but that's okay. The point is to initialize the *letsencrypt* environment for 
    subsequent invocations

6.  Initialize a directory in each of your website root document directories

    To use the *webroot* domain validation method, *letsencrypt* needs read/write
    permission to a special directory named *.well-known* (notice the dot) in your website. This is no problem if you run *letsencrypt* as *root*. But the *letsencrypt* server also needs to access to *.well-known* via your web server. 

    Since LW runs each virtual host with a specific non-root user id, you need to create a directory where *letsencrypt* can write files and your web server can read them. If you let the *letsencrypt* script create the *.well-known* directory, your web server will **not** have permission to read from it. Hence, you need to create it manually and adjust the ownship to accommodate your web server.

    If your virtual host runs as user *foo*:
    ``` bash
    cd /home/*foo*
    cd public_html
    mkdir .well-known
    chown *foo*:nobody .well-known
    ```

    If the virtual host runs Drupal you will need to create .well-known/.htaccess with these contents:

    ``` bash
    #
    # Override overly protective .htaccess in webroot
    #
    RewriteEngine On
    Satisfy Any
    ```

    Be sure to modify the ownership:

    ``` bash
    chown foo:nobody .wellknown/.htaccess
    ```

7. Run letsencrypt-auto for each cert you need to generate or renew
    Note: If your virtual host handles multiple domain names, you need to add them all to the same certificate. For exmample, if you have *example.com* and www<span></span>.example.com running on the same virtual host, include them both when you run letsencrypt-auto:

    ``` bash
    cd /root/letsencrypt
    ./letsencrypt-auto --text --agree-tos --email you@example.com certonly --renew-by-default --webroot --webroot-path /home/foo/public_html/ -d example.com -d www.example.com
    ```

    This creates or renews certificates that are now in */etc/letsencrypt/live*.

    To install these certificates using *whm* or *cpanel*, you can use the UI or automate it. 

# Automation<a name="automating"></a>
    
You can automate certificate renewal using cron. The *letsencrypt-lw.php* 
script in this project is an example for how to do this.

Main functions of this script:

* run *letsencrypt* with appropriate options to renew a certificate
* install the renewed certificate on the webserver using the cPanel API
* when running as a cron task, only output results for failures (to avoid
    having cron send you email when the job succeeds)

