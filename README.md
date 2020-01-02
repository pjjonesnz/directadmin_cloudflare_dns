# directadmin_cloudflare_dns
Script to sync DirectAdmin dns changes with your Cloudflare account

Cloudflare has a free domain nameserver service which is super fast. It is a great way to move nameserver hosting off your web server and on to a managed platform.

This handy script for DirectAdmin syncs the DNS records for your domains FROM DirectAdmin TO your Cloudflare account. Use this script when using Cloudflare as the nameserver host for your domain. 

PLEASE NOTE: this is a beta script released under the MIT License. While I use it in a production environment I make no guarantees to its suitability and stability for your server. Please test it thoroughly before using.

<h2>Installation (with helpful examples)</h2>

<ol>
  <li>SSH into your server (as admin)</li>
  <li>Download the repository to a folder on your server (example as follows)
    <ul>
      <li>cd /home/admin</li>
      <li>git clone https://github.com/pjjonesnz/directadmin_cloudflare_dns.git</li>
    </ul>
  </li>
  <li>Install the required composer packages
    <ul>
      <li>cd directadmin_cloudflare_dns/da_cloudflare_dns_sync</li>
      <li>Install Composer Dependency Manager for PHP if not already installed on your system (see: https://getcomposer.org/)</li>
      <li>run 'php composer.phar install' (or possibly 'composer install' if you have composer set up already)</li>
    </ul>
  </li>
  <li>At this point the composer dependencies will be downloaded into the vendor subfolder by composer and everything is ready to move into place in the DirectAdmin custom scripts folder</li>
  <li>Copy all the files in the main folder you created, including all subfolders, to /usr/local/directadmin/scripts/custom
    <ul>
        <li>Using the example folder structure given above, run the following</li>
        <li>sudo cp -r /home/admin/directadmin_cloudflare_dns/* /usr/local/directadmin/scripts/custom/</li>
    </ul>
  </li>
</ol>

<h2>Cloudflare setup</h2>

<ol>
  <li>Create a Cloudflare account if you haven't done so already at cloudflare.com</li>
  <li>Login to your Cloudflare account</li>
  <li>If you haven't used Cloudflare before, you will need to add one of your domains to Cloudflare to see the custom nameservers that have been assigned to your account. You're welcome to get a pro plan, but the free plan also works for this step.</li>
  <li>Once you have added your domain, you'll see the two nameservers that have been assigned to you in the format name.ns.cloudflare.com - make note of these for the DirectAdmin setup below</li>
  <li>Click on your profile icon at the top right of the website</li>
  <li>Click on 'My Profile'</li>
  <li>Select the 'API Tokens' tab</li>
  <li>Under the API Keys section, click on 'View' next to 'Global API Key'</li>
  <li>Type your password to view your API Key - make a safe note of this for the script setup below</li>
 </ol>
 
 <h2>Script setup</h2>
 
 <ol>
  <li>Edit the dns_write_post.sh file to add your email and API Key</li>
  <li>sudo vim /usr/local/directadmin/scripts/custom/dns_write_post.sh</li>
  <li>Edit the script to add your Cloudflare registered email address: eg. $cloudflare_email = 'email@domain.com';</li>
  <li>Edit the script to add your Cloudflare API Key: eg. $cloudflare_api_key='1234567890';</li>
  <li>Save the script and exit the editor</li>
  <li>Give dns_write_post.sh executable permissions: chmod 755 /usr/local/directadmin/scripts/custom/dns_write_post.sh</li>
  <li>Run the following command to verify the connection to Cloudflare
    <ul>
      <li>/usr/local/directadmin/scripts/custom/dns_write_post.sh verify</li>
      <li>You should see the following message, "Your user ID is: ......", with your user ID listed.</li>
    </ul>
  </li>
  <li>If so, CONGRATULATIONS, the script is installed and communicating with your Cloudflare account</li>
 </ol>
 
 <h2>DirectAdmin setup</h2>
 <ol>
  <li>Login to your DirectAdmin reseller or admin account</li>
  <li>Edit the DNS record for one of your websites. Change the nameservers for the domain to your two new Cloudflare nameservers</li>
  <li>Have a look at the DNS record on your Cloudflare account - if it is a different domain than you have previously added to your Cloudflare account, you will see that it has now been added, and any differences in the DNS record have been synchronized.</li>
  <li>Obviously if you are moving to Cloudflare's DNS hosting, you will need to update the nameserver records at your domain registrar.</li>
  </ol>
  
  <strong>That's it!</strong>
