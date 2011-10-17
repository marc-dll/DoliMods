<?php
/* Copyright (C) 2007-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2010      Jean-François FERRY  <jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 */

/**
 *   	\file       htdocs/ovh/admin/ovh_setup.php
 *		\ingroup    ovh
 *		\brief      Setup of module OVH
 *		\version    $Id: ovh_setup.php,v 1.19 2011/06/17 22:25:08 eldy Exp $
 */

define('NOCSRFCHECK',1);

$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res && file_exists("../../../dolibarr/htdocs/main.inc.php")) $res=@include("../../../dolibarr/htdocs/main.inc.php");     // Used on dev env only
if (! $res && file_exists("../../../../dolibarr/htdocs/main.inc.php")) $res=@include("../../../../dolibarr/htdocs/main.inc.php");   // Used on dev env only
if (! $res && file_exists("../../../../../dolibarr/htdocs/main.inc.php")) $res=@include("../../../../../dolibarr/htdocs/main.inc.php");   // Used on dev env only
if (! $res) die("Include of main fails");
include_once(DOL_DOCUMENT_ROOT."/lib/admin.lib.php");
dol_include_once("/ovh/class/ovhsms.class.php");
dol_include_once("/ovh/lib/ovh.lib.php");

// Load traductions files requiredby by page
$langs->load("admin");
$langs->load("companies");
$langs->load("ovh@ovh");
$langs->load("sms");

if (!$user->admin)
accessforbidden();
// Get parameters

$action=GETPOST('action');

// Protection if external user
if ($user->societe_id > 0)
{
    //accessforbidden();
}

$substitutionarrayfortest=array(
'__ID__' => 'TESTIdRecord',
'__LASTNAME__' => 'TESTLastname',
'__FIRSTNAME__' => 'TESTFirstname'
);


// Activate error interceptions
if (! empty($conf->global->MAIN_ENABLE_EXCEPTION))
{
    function traitementErreur($code, $message, $fichier, $ligne, $contexte)
    {
        if (error_reporting() & $code) {
            throw new Exception($message, $code);
        }
    }
    set_error_handler('traitementErreur');
}



/*
 * Actions
 */

if ($action == 'setvalue' && $user->admin)
{
    //$result=dolibarr_set_const($db, "PAYBOX_IBS_DEVISE",$_POST["PAYBOX_IBS_DEVISE"],'chaine',0,'',$conf->entity);
    $result=dolibarr_set_const($db, "OVHSMS_NICK",$_POST["OVHSMS_NICK"],'chaine',0,'',$conf->entity);
    $result=dolibarr_set_const($db, "OVHSMS_PASS",$_POST["OVHSMS_PASS"],'chaine',0,'',$conf->entity);
    $result=dolibarr_set_const($db, "OVHSMS_SOAPURL",$_POST["OVHSMS_SOAPURL"],'chaine',0,'',$conf->entity);


    if ($result >= 0)
    {
        $mesg='<div class="ok">'.$langs->trans("SetupSaved").'</div>';
    }
    else
    {
        dol_print_error($db);
    }
}



if ($action == 'setvalue_account' && $user->admin)
{
    $result=dolibarr_set_const($db, "OVHSMS_ACCOUNT",$_POST["OVHSMS_ACCOUNT"],'chaine',0,'',$conf->entity);

    if ($result >= 0)
    {
        $mesg='<div class="ok">'.$langs->trans("SetupSaved").'</div>';
    }
    else
    {
        dol_print_error($db);
    }
}

/* Envoi d'un SMS */
if ($action == 'send' && ! $_POST['cancel'])
{
    $error=0;

    $smsfrom='';
    if (! empty($_POST["fromsms"])) $smsfrom=GETPOST("fromsms");
    if (empty($smsfrom)) $smsfrom=GETPOST("fromname");
    $sendto     = GETPOST("sendto");
    $body       = GETPOST('message');
    $deliveryreceipt= GETPOST("deliveryreceipt");
    $deferred   = GETPOST('deferred');
    $priority   = GETPOST('priority');
    $class      = GETPOST('class');
    $errors_to  = GETPOST("errorstosms");

    // Create form object
    include_once(DOL_DOCUMENT_ROOT.'/core/class/html.formsms.class.php');
    $formsms = new FormSms($db);

    if (empty($body))
    {
        $mesg='<div class="error">'.$langs->trans("ErrorFieldRequired",$langs->transnoentities("Message")).'</div>';
        $action='testsms';
        $error++;
    }
    if (empty($smsfrom) || ! str_replace('+','',$smsfrom))
    {
        $mesg='<div class="error">'.$langs->trans("ErrorFieldRequired",$langs->transnoentities("SmsFrom")).'</div>';
        $action='testsms';
        $error++;
    }
    if (empty($sendto) || ! str_replace('+','',$sendto))
    {
        $mesg='<div class="error">'.$langs->trans("ErrorFieldRequired",$langs->transnoentities("SmsTo")).'</div>';
        $action='testsms';
        $error++;
    }
    if (! $error)
    {
        // Make substitutions into message
        $substitutionarrayfortest['__PHONEFROM__']=$smsfrom;
        $substitutionarrayfortest['__PHONETO__']=$sendto;
        complete_substitutions_array($substitutionarrayfortest,$langs);
        $body=make_substitutions($body,$substitutionarrayfortest);

        require_once(DOL_DOCUMENT_ROOT."/lib/CSMSFile.class.php");

        $smsfile = new CSMSFile($sendto, $smsfrom, $body, $deliveryreceipt, $deferred, $priority, $class);  // This define OvhSms->login, pass, session and account
        $result=$smsfile->sendfile(); // This send SMS

        if ($result > 0)
        {
            $mesg='<div class="ok">'.$langs->trans("SmsSuccessfulySent",$smsfrom,$sendto).'</div>';
        }
        else
        {
            $mesg='<div class="error">'.$langs->trans("ResultKo").'<br>'.$smsfile->error.'</div>';
        }

        $action='';
    }
}





/*
 * View
 */

llxHeader('','OvhSmsSetup','','');

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';

print_fiche_titre($langs->trans("OvhSmsSetup"),$linkback,'setup');

$head=ovhadmin_prepare_head();

dol_fiche_head($head, 'sms', $langs->trans("Ovh"));


print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="setvalue">';

if (!extension_loaded('soap'))
{
    print '<div class="error">'.$langs->trans("PHPExtensionSoapRequired").'</div>';
}

$var=true;

print '<table class="nobordernopadding" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print "</tr>\n";


$var=!$var;
print '<tr '.$bc[$var].'><td width="200px" class="fieldrequired">';
print $langs->trans("OvhSmsNick").'</td><td>';
print '<input size="64" type="text" name="OVHSMS_NICK" value="'.$conf->global->OVHSMS_NICK.'">';
print '<br>'.$langs->trans("Example").': AA123-OVH';
print '</td></tr>';


$var=!$var;
print '<tr '.$bc[$var].'><td class="fieldrequired">';
print $langs->trans("OvhSmsPass").'</td><td>';
print '<input size="64" type="password" name="OVHSMS_PASS" value="'.$conf->global->OVHSMS_PASS.'">';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'><td class="fieldrequired">';
print $langs->trans("OvhSmsSoapUrl").'</td><td>';
print '<input size="64" type="text" name="OVHSMS_SOAPURL" value="'.$conf->global->OVHSMS_SOAPURL.'">';
print '<br>'.$langs->trans("Example").': https://www.ovh.com/soapi/soapi-re-1.26.wsdl';
print '</td></tr>';

print '<tr><td colspan="2" align="center"><input type="submit" class="button" value="'.$langs->trans("Modify").'"></td></tr>';
print '</table></form>';

dol_fiche_end();


dol_htmloutput_mesg($mesg);



// Connect area
require_once(NUSOAP_PATH.'/nusoap.php');     // Include SOAP

$WS_DOL_URL = $conf->global->OVHSMS_SOAPURL;
dol_syslog("Will use URL=".$WS_DOL_URL, LOG_DEBUG);

if (empty($conf->global->OVHSMS_NICK) || empty($WS_DOL_URL))
{
    echo '<br>'.'<div class="warning">'.$langs->trans("OvhSmsNotConfigured").'</div>';
}
else
{

    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=test">'.$langs->trans("TestLoginToAPI").'</a><br><br>';

    if ($action == 'test')
    {
        require_once(DOL_DOCUMENT_ROOT.'/lib/functions2.lib.php');
        $params=getSoapParams();
        ini_set('default_socket_timeout', $params['response_timeout']);

        if ($params['proxy_use']) print $langs->trans("TryToUseProxy").': '.$params['proxy_host'].':'.$params['proxy_port'].($params['proxy_login']?(' - '.$params['proxy_login'].':'.$params['proxy_password']):'').'<br>';
        print 'URL: '.$WS_DOL_URL.'<br>';
        print $langs->trans("ConnectionTimeout").': '.$params['connection_timeout'].'<br>';
        print $langs->trans("ResponseTimeout").': '.$params['response_timeout'].'<br>';

        $soap = new SoapClient($WS_DOL_URL,$params);
        try {
            $language="en";
            $multisession=false;

            //login
            $session = $soap->login($conf->global->OVHSMS_NICK, $conf->global->OVHSMS_PASS, $language, $multisession);
            if ($session) print '<div class="ok">'.$langs->trans("OvhSmsLoginSuccessFull").'</div><br>';
            else print '<div class="error">Error login did not return a session id</div><br>';

            //logout
            $soap->logout($session);
            //  echo "logout successfull\n";

        }
        catch(Exception $e)
        {
            print '<div class="error">';
            print 'Error '.$e->getMessage().'<br>';
            print 'If this is an error to connect to OVH host, check your firewall does not block port required to reach OVH manager (for example port 1664).<br>';
            print '</div>';
        }
    }

    print '<br>';
}


if (! empty($conf->global->OVHSMS_NICK) && ! empty($WS_DOL_URL))
{
    // Formulaire d'ajout de compte SMS qui sera valable pour tout Dolibarr
    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="setvalue_account">';

    $var=true;

    print '<table class="nobordernopadding" width="100%">';
    print '<tr class="liste_titre">';
    print '<td width="200px">'.$langs->trans("Parameter").'</td>';
    print '<td>'.$langs->trans("Value").'</td>';
    print '<td>&nbsp;</td>';
    print "</tr>\n";


    $var=!$var;
    print '<tr '.$bc[$var].'><td class="fieldrequired">';
    print $langs->trans("OvhSmsLabelAccount").'</td><td>';
    print '<input size="64" type="text" name="OVHSMS_ACCOUNT" value="'.$conf->global->OVHSMS_ACCOUNT.'">';
    print '<br>'.$langs->trans("Example").': sms-aa123-1';
    print '<td>'.'<a href="ovh_smsrecap.php" target="_blank">'.$langs->trans("ListOfSmsAccountsForNH").'</a>';

    print '</td></tr>';

    print '<tr><td colspan="3" align="center"><input type="submit" class="button" value="'.$langs->trans("Modify").'"></td></tr>';
    print '</table></form>';


    if ($action != 'testsms')
    {
        if (! empty($conf->global->OVHSMS_ACCOUNT))
        {
            print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=testsms">'.$langs->trans("DoTestSend").'</a>';
        }
        else
        {
            print '<a class="butActionRefused" href="#">'.$langs->trans("DoTestSend").'</a>';
        }
    }
    else
    {
        print '<br>';

        print_fiche_titre($langs->trans("Sms"));

        // Cree l'objet formulaire mail
        include_once(DOL_DOCUMENT_ROOT.'/core/class/html.formsms.class.php');
        $formsms = new FormSms($db);
        $formsms->fromtype = 'user';
        $formsms->fromid   = $user->id;
        $formsms->fromname = $user->getFullName($langs);
        $formsms->fromsms = $user->user_mobile;
        $formsms->withfrom=(empty($_POST['fromsms'])?1:$_POST['fromsms']);
        $formsms->withfromreadonly=0;
        $formsms->withto=(empty($_POST["sendto"])?($user->user_mobile?$user->user_mobile:1):$_POST["sendto"]);
        $formsms->withbody=$langs->trans("SmsTestMessage");
        $formsms->withcancel=1;
        // Tableau des substitutions
        $formsms->substit=$substitutionarrayfortest;
        // Tableau des parametres complementaires du post
        $formsms->param['action']='send';
        $formsms->param['models']='body';
        $formsms->param['id']=0;
        $formsms->param['returnurl']=$_SERVER["PHP_SELF"];

        $formsms->show_form();

        print '<br>';
    }

    print '<br><br>';
}

/*
if ($action=='testsms')
{
    // Cree l'objet formulaire mail
    include_once(DOL_DOCUMENT_ROOT.'/core/class/html.formsms.class.php');
    $formsms = new FormSms($db);
    $formsms->fromtype = 'user';
    $formsms->fromid   = $user->id;
    $formsms->fromname = $user->getFullName($langs);
    $formsms->fromsms = $user->user_mobile;
    $formsms->withfrom=1;
    $formsms->withfromreadonly=0;
    $formsms->withto=empty($_POST["sendto"])?1:$_POST["sendto"];
    $formsms->withbody=1;
    $formsms->withcancel=1;
    // Tableau des substitutions
    $formsms->substit['__FACREF__']=$object->ref;
    // Tableau des parametres complementaires du post
    $formsms->param['action']=$action;
    $formsms->param['models']=$modelmail;
    $formsms->param['facid']=$object->id;
    $formsms->param['returnurl']=$_SERVER["PHP_SELF"].'?id='.$object->id;

    $formsms->show_form();

    print '<br>';
}
*/


// End of page
$db->close();

llxFooter('');
?>
