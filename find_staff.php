<?php
require('simple_html_dom.php');

//configure users and init other vars

$techs = array(
    "user1" => array(
        "MAC" => "AA AA AA AA AA AA",
        "Site" => "",
        "Time" => "",
    ),
    "user2" => array(
        "MAC" => "BB BB BB BB BB BB",
        "Site" => "",
        "Time" => "",
    ),
    "user3" => array(
        "MAC" => "CC CC CC CC CC CC",
        "Site" => "",
        "Time" => "",
    )
);

//init sites list
//all sites are identified by their second octet
//(ie. site A only contains IP addresses like 10.1.X.X, site b 10.2.X.X

$sites = array(
   "1" => "Site A",
   "2" => "Site B",
   "3" => "Site C"
);

//get any existing values from the existing page
$html = file_get_html('https://www.website.com/inout.html');
$trs = $html->find('table',0)->find('tr');
foreach ($trs as $tr) {
  $name = strip_tags($tr->find('td',0));
  $site = strip_tags($tr->find('td',1));
  $time = strip_tags($tr->find('td',2));
  ${$name}['Site'] = $site;
  ${$name}['Time'] = $time;
} 

//build the mac address search string (grab the mac address from each tech separated by a pipe)

$allmacs = "";
foreach ( $techs as $tech ) {
  $allmacs = $allmacs . "\"" .$tech['MAC'] . "\"" . "\|";
}
$allmacs = rtrim($allmacs, "|");

//query the controller and only grab entries we're interested in
//use proper SNMP credentials

$oidcmd = "snmpwalk -v3 -u snmpuser -A snmppassword -l authNoPriv -a MD5 ruc.kus.ip  1.3.6.1.4.1.25053.1.2.2.1.1.3.1.1.1 |grep -E $allmacs"; $oidlist = shell_exec($oidcmd);

//turn on output buffering to start building our HTML
ob_start();
echo "<html>
<head>
<title>
In Out Board
</title>
</head>
<body>
<table style=\"width:100%;text-align:center;font-size:300%\">
<tr><th>Name</th><th>Site</th><th>Last Seen</th></tr> ";

//start working through every tech in the list
foreach ( $techs as $technician => $tech ) {
  $oid = "";
  //for each line returned earlier, only get the one we're interested in now
  foreach(preg_split("/((\r?\n)|(\r\n?))/", $oidlist) as $line){
    if (strpos($line, $tech['MAC'])) {
      $oid = $line;
    }
  }
  //if we found data, get the ip address associated with the MAC
  //also lookup the site they're at and get the current time
  //if no data, don't touch the site or time variables from the existing file
  if ($oid != "") {
    $clientoid = get_string_between($oid, "25053.1.2.2.1.1.3.1.1.1", " = Hex");
    $clientoid = "1.3.6.1.4.1.25053.1.2.2.1.1.3.1.1.8" . $clientoid;
    $getcmd = "snmpget -v3 -u snmpuser -A snmppassword -l authNoPriv -a MD5 ruc.kus.ip  " . $clientoid;
    $clientip = shell_exec($getcmd);
    $clientip = get_string_between($clientip, "IpAddress: ", "\n");
    //get the 2nd octet of their IP address, modify this if your ip schema is different
    $clientnet = get_string_between($clientip,"10.", ".");
    ${$technician}['Site'] = $sites[$clientnet];
    ${$technician}['Time'] = date("g:ia M d");
  } else {}

echo "<tr><td>";
echo $technician;
echo "</td><td>";
echo ${$technician}['Site'];
echo "</td><td>";
echo ${$technician}['Time'];
echo  "</td></tr>
";
}

echo "</table>
<br /><br /><br /><br />
Updated: ";
echo date("h:ia Y-m-d");
echo "</body>
</html>";

//write out the file somewhere
file_put_contents('/var/www/html/inout.html', ob_get_contents());
ob_end_clean();

function get_string_between($string, $start, $end){
    $string = " ".$string;
    $ini = strpos($string,$start);
    if ($ini == 0) return "";
    $ini += strlen($start);
    $len = strpos($string,$end,$ini) - $ini;
    return substr($string,$ini,$len);
}

?>
