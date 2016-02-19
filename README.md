# letsencrypt
Instructions and code for running letsencrypt on a CentOS 6 virtual private server

In a nutshell:

1. Install the Epel Repository
2. Install the IUS Repository
3. Install Python 2.7 and Git
4. Clone the letsencrypt git repository into the root home directory
5. Run letsencrypt-auto to setup the environment local to the root user
6. Initialize directories in each of your web root documents for letsencrypt
7. Run letsencrypt-auto for each cert you need to generate or renew

Important notes:
1. You do steps 1-6 just once. 
2. Step 6 is needed to allow letsencrypt to run without permission problems (404 errors). Also, if the particular website is running Drupal, you need to create a special .well-known/.htaccess file
3. You can automate step 7 in cron jobs and scripts. Since the certificates are good only for 90 days, you need to renew them more frequently, either manually or via cron.

Details:

1. Install the Epel Repository: 
yum install epel-release

2. Install the IUS Repository:
rpm -ivh https://rhel6.iuscommunity.org/ius-release.rpm

3. Install Python 2.7 and Git:
yum --enablerepo=ius install git python27 python27-devel python27-pip python27-setuptools python27-virtualenv -y

4. Clone the letsencrypt git repository into the root home directory:
cd /root
git clone https://github.com/letsencrypt/letsencrypt

5. Run letsencrypt-auto to setup the environment local to the root user:
cd /root/letsencrypt
./letsencrypt-auto --verbose
# The above will likely fail with some message like "No installers are available...", 
# but that's okay. The point is to initialize the letsencrypt environment for 
# subsequent invocations

6.  Initialize directories in each of your web root documents for letsencrypt
There are different ways to run letsencrypt to get certificates. This recipe uses the
"webroot" method which allows letsencrypt to use an existing apache/ngnix service to
continue running. Since LW runs each virtual host with a specific non-root user id,
you need to create a directory where letsencrypt can write files.

If your virtual host runs as user foo:
cd /home/foo
cd www 
mkdir .well-known
chown foo:nobody .well-known

If the virtual host runs Drupal you will need to create .well-known/.htaccess with
these contents:
#
# Override overly protective .htaccess in webroot
#
RewriteEngine On
Satisfy Any

Be sure to modify the ownership:
chown foo:nobody .wellknown/.htaccess

7. Run letsencrypt-auto for each cert you need to generate or renew
Note: If your virtual host handles multiple domain names, you need to add them all to the same certificcate. For exmample, if you have example.com and www.example.com running on the same virtual host, include them both when you run letsencrypt-auto:

cd /root/letsencrypt
./letsencrypt-auto --text --agree-tos --email you@example.com certonly --renew-by-default --webroot --webroot-path /home/foo/public_html/ -d example.com -d www.example.com

This creates or renews certificates that are now in /etc/letsencrypt/live

To install these certificates using whm or cpanel, you can use the UI or automate it.

