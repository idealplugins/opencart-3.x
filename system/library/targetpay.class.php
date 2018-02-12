<?php

/**
 * @file     Provides support for TargetPay iDEAL, Mister Cash and Sofort Banking
 * @author     Yellow Melon B.V.
 * @url         http://www.idealplugins.nl
 * @release     29-09-2014
 * @ver         2.5
 *
 * Changes:
 *
 * v2.1    Cancel url added
 * v2.2     Verify Peer disabled, too many problems with this
 * v2.3    Added paybyinvoice (achteraf betalen) and paysafecard (former Wallie)
 * v2.4        Removed IP_range and deprecated checkReportValidity . Because it is bad practice.
 * v2.5        Added creditcards by ATOS
 */

/**
 * @class TargetPay Core class
 */
class TargetPayCore
{
    // Constants
    const APP_ID = "dw_opencart2.x_1.0.0";

    const DEFAULT_RTLO = 93929;

    const TARGETPAY_PREFIX = "targetpay_";

    const METHOD_SOFORT = "sofort";

    const METHOD_IDEAL = "ideal";

    const METHOD_PAYSAFE = "paysafecard";

    const METHOD_CREDIT_CARD = "creditcard";

    const METHOD_MRCASH = "mrcash";

    const METHOD_AFTERPAY = "afterpay";

    const METHOD_BANKWIRE = "bankwire";

    const METHOD_PAYPAL = "paypal";

    const MIN_AMOUNT = 84;

    const ERR_NO_AMOUNT = "Geen bedrag meegegeven | No amount given";

    const ERR_NO_DESCRIPTION = "Geen omschrijving meegegeven | No description given";

    const ERR_AMOUNT_TOO_LOW = "Bedrag is te laag | Amount is too low";

    const ERR_NO_RTLO = "Geen rtlo (layoutcode TargetPay) bekend; controleer de module instellingen |
        No rtlo (layoutcode TargetPay) filled in, check the module settings";

    const ERR_NO_TXID = "Er is een onjuist transactie ID opgegeven | An incorrect transaction ID was given";

    const ERR_NO_RETURN_URL = "Geen of ongeldige return URL | No or invalid return URL";

    const ERR_NO_REPORT_URL = "Geen of ongeldige report URL | No or invalid report URL";

    const ERR_IDEAL_NO_BANK = "Geen bank geselecteerd voor iDEAL | No bank selected for iDEAL";

    const ERR_SOFORT_NO_COUNTRY = "Geen land geselecteerd voor Sofort | No country selected for Sofort";

    const ERR_PAYBYINVOICE = "Fout bij achteraf betalen|Error with paybyinvoice";
    
    const COUNTRY_LIST = [
        '49' => 'Oostenrijk',
        'BE' => 'België',
        'CH' => 'Zwitserland',
        'DE' => 'Duitsland',
        'IT' => 'Italië',
        'NL' => 'Nederland',
    ];
    // Constant array's
    protected $paymentOptions = array(
        "IDE", 
        "MRC", 
        "DEB", 
        "AFT", 
        "WAL", 
        "CC", 
        "PYP", 
        "BW", 
        "AFP");

    /*
     * If payMethod is set to 'AUTO' it will decided on the value of bankId
     * Then, when requested the bankId list will be filled with
     *
     * a) 'IDE' + the bank ID's for iDEAL
     * b) 'MRC' for Mister Cash
     * c) 'DEB' + countrycode for Sofort Banking, e.g. DEB49 for Germany
     */
    protected $minimumAmounts = array(
        "IDE" => 84, 
        "MRC" => 49, 
        "DEB" => 10, 
        "AFT" => 1, 
        "WAL" => 10, 
        "CC" => 100, 
        "PYP" => 84, 
        "BW" => 84, 
        "AFP" => 500);

    private $startAPIs = [
        "IDE" => "https://transaction.digiwallet.nl/ideal/start", 
        "MRC" => "https://transaction.digiwallet.nl/mrcash/start", 
        "DEB" => "https://transaction.digiwallet.nl/directebanking/start", 
        "WAL" => "https://transaction.digiwallet.nl/paysafecard/start", 
        "CC" => "https://transaction.digiwallet.nl/creditcard/start", 
        "PYP" => "https://transaction.digiwallet.nl/paypal/start", 
        "AFP" => "https://transaction.digiwallet.nl/afterpay/start", 
        "BW" => "https://transaction.digiwallet.nl/bankwire/start"];
    
    private $checkAPIs = [
        "IDE" => "https://transaction.digiwallet.nl/ideal/check",
        "MRC" => "https://transaction.digiwallet.nl/mrcash/check",
        "DEB" => "https://transaction.digiwallet.nl/directebanking/check",
        "AFT" => "https://transaction.digiwallet.nl/afterpay/check",
        "WAL" => "https://transaction.digiwallet.nl/paysafecard/check",
        "CC" => "https://transaction.digiwallet.nl/creditcard/check",
        "PYP" => "https://transaction.digiwallet.nl/paypal/check",
        "AFP" => "https://transaction.digiwallet.nl/afterpay/check",
        "BW" => "https://transaction.digiwallet.nl/bankwire/check"];
    
    
    // Variables
    protected $rtlo = null;

    protected $testMode = false;

    protected $language = "nl";

    protected $payMethod = "";
    
    // Payment Method
    protected $currency = "EUR";

    protected $bankId = null;

    protected $amount = 0;

    protected $description = null;

    protected $returnUrl = null;
    
    // When using the AUTO-setting; %payMethod% will be replaced by the actual payment method just before starting the payment
    protected $cancelUrl = null;
    
    // When using the AUTO-setting; %payMethod% will be replaced by the actual payment method just before starting the payment
    protected $reportUrl = null;
    
    // When using the AUTO-setting; %payMethod% will be replaced by the actual payment method just before starting the payment
    protected $bankUrl = null;

    protected $transactionId = null;

    protected $paidStatus = false;

    protected $errorMessage = null;

    protected $parameters = array();
    // Additional parameters
    
    // bankwire more info
    public $moreInformation;
    // bankwire salt
    public $bwSalt = 'ou749hy';

    protected $bankwireAmountDue = null;
    protected $bankwireAmountPaid = null;

    /**
     * Constructor
     *
     * @param int $rtlo
     *            Layoutcode
     */
    public function __construct($payMethod, $rtlo = false, $language = "nl", $testMode = false)
    {
        $payMethod = strtoupper($payMethod);
        if (in_array($payMethod, $this->paymentOptions)) {
            $this->payMethod = $payMethod;
        } else {
            return false;
        }
        $this->rtlo = (int) $rtlo;
        $this->testMode = ($testMode) ? '1' : '0';
        $this->language = strtolower(substr($language, 0, 2));
    }

    /**
     * Get list with banks based on PayMethod setting (IDE, ...
     * etc.)
     */
    public function getBankList()
    {
        $url = 'https://transaction.digiwallet.nl/ideal/getissuers?ver=4&format=xml';
        $banks_array = [];
        
        $xml = $this->httpRequest($url);
        if (! $xml) {
            $banks_array["IDE0001"] = "Bankenlijst kon niet opgehaald worden bij TargetPay, controleer of curl werkt!";
            $banks_array["IDE0002"] = "  ";
        } else {
            $p = xml_parser_create();
            $banks_object = null;
            xml_parse_into_struct($p, $xml, $banks_object, $index);
            xml_parser_free($p);
            foreach ($banks_object as $bank) {
                if (empty($bank['attributes']['ID']))
                    continue;
                $banks_array[$bank['attributes']['ID']] = $bank['value'];
            }
        }
        
        return $banks_array;
    }
    
    public function getCountryList()
    {
        return self::COUNTRY_LIST;
    }

    /**
     * PRIVATE FUNCTIONS
     */
    protected function httpRequest($url, $method = "GET")
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if ($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    /**
     * Start transaction with TargetPay
     *
     * Set at least: amount, description, returnUrl, reportUrl (optional: cancelUrl)
     * In case of iDEAL: bankId
     * In case of Sofort: countryId
     *
     * After starting, it will return a link to the bank if successfull :
     * - Link can also be fetched with getBankUrl()
     * - Get the transaction id via getTransactionId()
     * - Read the errors with getErrorMessage()
     * - Get the actual started payment method, in case of auto-setting, using getPayMethod()
     */
    public function startPayment()
    {
        if (! $this->rtlo) {
            $this->errorMessage = self::ERR_NO_RTLO;
            return false;
        }
        
        if (! $this->amount) {
            $this->errorMessage = self::ERR_NO_AMOUNT;
            
            return false;
        }
        
        if ($this->amount < $this->minimumAmounts[$this->payMethod]) {
            $this->errorMessage = self::ERR_AMOUNT_TOO_LOW;
            
            return false;
        }
        
        if (! $this->description) {
            $this->errorMessage = self::ERR_NO_DESCRIPTION;
            
            return false;
        }
        
        if (! $this->returnUrl) {
            $this->errorMessage = self::ERR_NO_RETURN_URL;
            
            return false;
        }
        
        if (! $this->reportUrl) {
            $this->errorMessage = self::ERR_NO_REPORT_URL;
            
            return false;
        }
        
        if (($this->payMethod == "IDE") && (! $this->bankId)) {
            $this->errorMessage = self::ERR_IDEAL_NO_BANK;
            
            return false;
        }
        
        if (($this->payMethod == "DEB") && (! $this->countryId)) {
            $this->errorMessage = self::ERR_SOFORT_NO_COUNTRY;
            
            return false;
        }
        
        $this->returnUrl = str_replace("%payMethod%", $this->payMethod, $this->returnUrl);
        $this->cancelUrl = str_replace("%payMethod%", $this->payMethod, $this->cancelUrl);
        $this->reportUrl = str_replace("%payMethod%", $this->payMethod, $this->reportUrl);
        $url = "";
        // Startpayment Url builder
        $url = $this->startAPIs[$this->payMethod] . "?rtlo=" . urlencode($this->rtlo);
        $url .= "&bank=" . urlencode($this->bankId);
        $url .= "&amount=" . urlencode($this->amount);
        $url .= "&description=" . urlencode($this->description);
        $url .= "&test=" . $this->testMode;
        $url .= "&userip=" . urlencode($_SERVER["REMOTE_ADDR"]);
        $url .= "&domain=" . urlencode($_SERVER["HTTP_HOST"]);
        $url .= "&returnurl=" . urlencode($this->returnUrl);
        $url .= "&reporturl=" . urlencode($this->reportUrl);
        $url .= "&app_id=" . urlencode(self::APP_ID);
        $url .= ((! empty($this->salt)) ? "&salt=" . urlencode($this->salt) : "");
        $url .= ((! empty($this->cancelUrl)) ? "&cancelurl=" . urlencode($this->cancelUrl) : "");
        // Case by case
        $url .= (($this->payMethod == "BW") ? "&ver=2" : "");
        $url .= (($this->payMethod == "PYP") ? "&ver=1" : "");
        $url .= (($this->payMethod == "AFP") ? "&ver=1" : "");
        $url .= (($this->payMethod == "IDE") ? "&ver=4" : "");
        $url .= (($this->payMethod == "CC") ? "&ver=2" : "");
        $url .= (($this->payMethod == "MRC") ? "&ver=2&lang=" . urlencode($this->getLanguage(array(
            "NL", 
            "FR", 
            "EN"), "NL")) : "");
        $url .= (($this->payMethod == "DEB") ? "&ver=2&type=1&country=" . urlencode($this->countryId) . "&lang=" . urlencode($this->getLanguage(array(
            "NL", 
            "EN", 
            "DE"), "DE")) : "");
        
        if (is_array($this->parameters)) {
            foreach ($this->parameters as $k => $v) {
                $url .= "&" . $k . "=" . urlencode($v);
            }
        }

        $result = $this->httpRequest($url);
        
        if (substr($result, 0, 6) == "000000" || substr($result, 0, 6) == "000001") {
            $result = substr($result, 7);
            
            if ($this->payMethod == 'AFP') {
                list ($this->transactionId, $status, $this->bankUrl) = explode("|", $result);
            } else {
                list ($this->transactionId, $this->bankUrl) = explode("|", $result); // the rest payment methods
            }
            
            if ($this->payMethod == 'BW') {
                $this->moreInformation = $result;
                return true;
            }
            
            return $this->bankUrl;
        }
        
        $this->errorMessage = "TargetPay antwoordde: " . $result . " | TargetPay responded with: " . $result;
        return false;
    }

    public function getLanguage($allowList = false, $defaultLanguage = false)
    {
        if (! $allowList) {
            return $this->language;
        } else {
            if (in_array(strtoupper($this->language), $allowList)) {
                return strtoupper($this->language);
            } else {
                return $this->defaultLanguage;
            }
        }
    }

    /**
     * Check transaction with TargetPay
     *
     * @param string $payMethodId
     *            Payment method's see above
     * @param string $transactionId
     *            Transaction ID to check
     *            
     *            Returns true if payment successfull (or testmode) and false if not
     *            
     *            After payment:
     *            - Read the errors with getErrorMessage()
     *            - Get user information using getConsumerInfo()
     *            
     */
    public function checkPayment($transactionId)
    {
        if (! $this->rtlo) {
            $this->errorMessage = self::ERR_NO_RTLO;
            
            return false;
        }
        
        if (! $transactionId) {
            $this->errorMessage = self::ERR_NO_TXID;
            
            return false;
        }
        
        $url = $this->checkAPIs[$this->payMethod];
        $url .= "?rtlo=" . urlencode($this->rtlo) . "&";
        $url .= "trxid=" . urlencode($transactionId) . "&";
        $url .= "test=" . (($this->testMode) ? "1" : "0") . "&";
        $url .= "once=0";
        
        $params = [];
        if ($this->payMethod == 'BW') {
            $params['checksum'] = md5($transactionId . $this->rtlo . $this->bwSalt);
        }
        if (! empty($params)) {
            foreach ($params as $k => $v) {
                $url .= "&" . $k . "=" . urlencode($v);
            }
        }

        return $this->parseCheckApi($this->httpRequest($url));
    }

    /*
     * Bankwire: 000000 OK|750|795
     *
     * After pay:
     * 000000 invoiceKey|invoicePaymentReference|status
     * 000000 invoiceKey|invoicePaymentReference|status|enrichmentURL
     * 000000 invoiceKey|invoicePaymentReference|status|rejectionReason|rejectionMessages
     *
     * sofort, creditcard, ideal, mistercash, paypal, paysafecard: 000000 OK
     * Error case show entire error message
     * update 2017.09.28: creditcard return 000001 for succeed in test mode
     */
    public function parseCheckApi($strResult)
    {
        if ($this->testMode) {
            $this->paidStatus = true;

            return true;
        }

        $_result = explode("|", $strResult);
        @list($resultCode, $additionalParam1, $additionalParam2) = $_result;

        if (trim($resultCode) == "000000 OK" && is_numeric($additionalParam1) && is_numeric($additionalParam2)) {
            // BankWire response
            $this->paidStatus = true;
            $this->bankwireAmountDue = (int)$additionalParam1;
            $this->bankwireAmountPaid = (int)$additionalParam2;

            return true;
        }
        if (trim($resultCode) == "000000 OK" || (substr(trim($resultCode), 0, 6) == "000000" && trim($additionalParam2) == 'Captured')) {
            // AfterPay response
            $this->paidStatus = true;

            return true;
        }

        $this->paidStatus = false;
        $this->errorMessage = $strResult;

        return false;
    }

    /**
     * Bind additional parameter to start request.
     * Safe for chaining.
     */
    public function bindParam($name, $value)
    {
        $this->parameters[$name] = $value;
        
        return $this;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * GETTERS & SETTERS
     */
    public function setAmount($amount)
    {
        $this->amount = round($amount);
        
        return true;
    }

    public function getBankId()
    {
        return $this->bankId;
    }

    public function setBankId($bankId)
    {
        $this->bankId = $bankId;
    }

    public function getBankUrl()
    {
        return $this->bankUrl;
    }

    public function setCountryId($countryId)
    {
        $this->countryId = $countryId;
        return true;
    }

    public function getCountryId()
    {
        return $this->countryId;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function setCurrency($currency)
    {
        $this->currency = strtoupper(substr($currency, 0, 3));
        
        return true;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = substr($description, 0, 32);
        
        return true;
    }

    public function getErrorMessage()
    {
        $returnVal = '';
        if (! empty($this->errorMessage)) {
            if ($this->language == "nl" && strpos($this->errorMessage, " | ") !== false) {
                list($returnVal) = explode(" | ", $this->errorMessage, 2);
            } elseif ($this->language == "en" && strpos($this->errorMessage, " | ") !== false) {
                list($discard, $returnVal) = explode(" | ", $this->errorMessage, 2);
            } else {
                $returnVal = $this->errorMessage;
            }
        }
        
        return $returnVal;
    }

    public function getPaidStatus()
    {
        return $this->paidStatus;
    }

    public function getPayMethod()
    {
        return $this->payMethod;
    }

    public function getReportUrl()
    {
        return $this->reportUrl;
    }

    public function setReportUrl($reportUrl)
    {
        if (preg_match('|(\w+)://([^/:]+)(:\d+)?(.*)|', $reportUrl)) {
            $this->reportUrl = $reportUrl;
            
            return true;
        } else {
            return false;
        }
    }

    public function getReturnUrl()
    {
        return $this->returnUrl;
    }

    public function setReturnUrl($returnUrl)
    {
        if (preg_match('|(\w+)://([^/:]+)(:\d+)?(.*)|', $returnUrl)) {
            $this->returnUrl = $returnUrl;
            
            return true;
        } else {
            return false;
        }
    }

    public function getCancelUrl()
    {
        return $this->cancelUrl;
    }

    public function setCancelUrl($cancelUrl)
    {
        if (preg_match('|(\w+)://([^/:]+)(:\d+)?(.*)|', $cancelUrl)) {
            $this->cancelUrl = $cancelUrl;
            
            return true;
        } else {
            return false;
        }
    }

    public function getTransactionId()
    {
        return $this->transactionId;
    }

    public function setTransactionId($transactionId)
    {
        $this->transactionId = substr($transactionId, 0, 32);
        
        return true;
    }

    public function getBankwireAmountDue()
    {
        return $this->bankwireAmountDue;
    }

    public function getBankwireAmountPaid()
    {
        return $this->bankwireAmountPaid;
    }

    public static function getNewVersionDataQuery($method)
    {
        $table = DB_PREFIX . self::TARGETPAY_PREFIX . $method->getMethodName();
        return "select order_id from $table";
    }

    public static function getCreateTableQuery($method)
    {
        $table = DB_PREFIX . self::TARGETPAY_PREFIX . $method->getMethodName();
        
        $sql = "CREATE TABLE IF NOT EXISTS `" . $table . "` (
        `order_id` VARCHAR(64) NOT NULL DEFAULT '',
        `method` VARCHAR(6) DEFAULT NULL,
        `{$method->getMethodName()}_txid` VARCHAR(64) NOT NULL DEFAULT '',
        `{$method->getMethodName()}_response` VARCHAR(128) DEFAULT NULL,
        `paid` DATETIME DEFAULT NULL,
        PRIMARY KEY (`order_id`, `{$method->getMethodName()}_txid`))";
        
        return $sql;
    }

    public static function getOldTableCheckQuery($method)
    {
        return "SHOW TABLES LIKE '" . DB_PREFIX . "{$method->getMethodName()}'";
    }
    
    /**
     * Handle tax rate for Afterpay method
     * https://www.digiwallet.nl/documentation/afterpay?id=afterpay&lang=en
     * @param unknown $rate
     * @return number
     */
    public function getTax($rate = null)
    {
        if(empty($rate)) return 4; // No tax
        else if($rate >= 21) return 1;
        else if($rate >= 6) return 2;
        else return 3;
    }
}
