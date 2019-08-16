<?php

//NowPayments Api
class Api {
	var $key;
	const URL = "https://api.nowpayments.io/v1";
	
	function apiStatus() {
		$link = self::URL."/status";
		return $this->request($link);
	}

	function getCurrencies() {
		$link = self::URL."/currencies";
		return $this->request($link);
	}

	function getEstimatedPrice($amount, $c_from, $c_to) {
		$link = self::URL."/estimate?amount=".$amount."&currency_from=".$c_from."&currency_to=".$c_to;
		return $this->request($link);
	}
	
	function setKey($key) {
		$this->key = $key;
	}
	
	function getKey() {
		return $this->key;
	}
	
	//Curl request
	function request($link) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $link);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'x-api-key: '.$this->key,
		));
		$output = curl_exec($ch);
		curl_close($ch);
		
		try {
			$output = json_decode($output, true);
		} catch (Exception $e) {
			throwError('PHP error occured: ',  $e->getMessage(), "\n");
		}
		$this->checkResponse($output);
		
		return $output;
  }
  
  function checkResponse($output) {
		if($output === NULL) {
			throwError("Something went wrong, please contact the developer of Nowpayments Plugin");
		}
		else if (isset($output["message"]) && $output["message"] == "Invalid access token") {
			throwError("Invlaid Nowpayments Api key. Please change your Api key in settings");
		}
		return;
	}

}

//Show error on page
function throwError($str) {
  echo "
  <html>
  <head>
    <meta charset='UTF-8' />
    <link rel='stylesheet' href='css/error.css'>
  </head>
  <body>
    <div class='error'>
      ".$str."
    </div>
  </body>
  </html>
  ";
	exit();
}

//Data processing
function clean($value = "") {
    $value = trim($value);
    $value = stripslashes($value);
    $value = strip_tags($value);
    $value = htmlspecialchars($value);
	return $value;
}

//Data
$base_currencies = ["usd", "eur", "nzd"];
$total = 0;
$subtotal = 0;
$currency = clean($_GET["currency"]);
$order_id = clean($_GET["order_id"]);
$invoice = clean($_GET["invoice"]);
$success_url = $_GET["success_url"];
try {
	$items = json_decode($_GET["items"], true);
} catch (Exception $e) {
	throwError('PHP error occured: ',  $e->getMessage(), "\n");
}
$name = clean($_GET["first_name"])." ".clean($_GET["last_name"]);
$email = clean($_GET["email"]);
$shipping = clean($_GET["shippingf"]);
$tax = clean($_GET["taxf"]);

$api = new Api;
$key = clean($_GET["api_key"]);
$status = $api->apiStatus();

//Errors
if (strtolower($status["message"]) != "ok") {
	throwError("Something wrong with Nowpayments Api");
}
if (preg_match("/[A-Z0-9]{7}-[A-Z0-9]{7}-[A-Z0-9]{7}-[A-Z0-9]{7}/", $key)) {
	$api->setKey($key);
} else {
	throwError("Invlaid Nowpayments Api key. Please change your Api key in settings");
}
if (!in_array(strtolower($currency), $base_currencies)) {
	throwError("Please change your currency to one of this:\n".implode(', ', $base_currencies));
}
if (!isset($items) || $items === NULL) {
	throwError("Please go back and add items in a cart");
}
if (!preg_match("/.+@.+\..+/", $email) || !preg_match("/[a-zA-Z0-9 ]+/", $name)) {
	throwError("Something wrong with your personal information");
}


//Items in html			
foreach($items as $item => $values) {
	if ($values["quantity"] < 1) continue;
	$subtotal += clean($values["total"])+clean($values["total_tax"]);
}
$total = $subtotal + $shipping + $tax;

//Totals in html
$subtotal_html = number_format($subtotal, 2, '.', ' ') . " " . strtoupper($currency);
$shipping_html = number_format($shipping, 2, '.', ' ') . " " . strtoupper($currency);
$tax_html = number_format($tax, 2, '.', ' ') . " " . strtoupper($currency);
$total_html = number_format($total, 2, '.', ' ') . " " . strtoupper($currency);

//Currencies in html
$currencies = $api->getCurrencies();
$currencies_html = [];
$i = 0;
foreach ($currencies["currencies"] as $curr) {								
	$estimate = $api->getEstimatedPrice($total, "usd", $curr);
	if(isset($estimate["estimated_amount"])) {
		if($curr == "btc") $tag = "tag";
		else $tag = "";
		$currencies_html[$i]["tag"] = $tag;
		$currencies_html[$i]["curr"] = $curr;
		$currencies_html[$i]["estimate"] = $estimate["estimated_amount"];
		$currencies_html[$i]["estimate_str"] = $estimate["estimated_amount"]." ".strtoupper($curr);
		$i++;
	}
}
?>
<html>

<head>
  <meta charset="UTF-8" />
  <link rel="stylesheet" href="css/style.css">
  <script src="http://code.jquery.com/jquery-latest.min.js"></script>
</head>

<body>
  <div class="container">
    <div class="payment-checkout">
      <table class="order table table_bordered">
        <tr>
          <th class="table__header table__header_primary">
            Item
          </th>
          <th class="table__header table__header_primary">
            Price per item
          </th>
          <th class="table__header table__header_primary">
            Quantity
          </th>
          <th class="table__header table__header_primary">
            Price
          </th>
        </tr>
        <?php foreach($items as $item => $values) {
						if ($values["quantity"] < 1) continue; ?>

        <tr>
          <td>
            <?php echo clean($values["name"]); ?>
          </td>
          <td>
            <?php echo (clean($values["subtotal"])+clean($values["subtotal_tax"]))/clean($values["quantity"]); ?>
          </td>
          <td>
            <?php echo clean($values["quantity"]); ?>
          </td>
          <td>
            <?php echo clean($values["total"])+clean($values["total_tax"]); ?>
          </td>
        </tr>
        <?php } ?>
      </table>

      <div class="row">
        <table class="table buyer-info">
          <tr>
            <th class="table__header">
              <div class="table__heading">
                Buyer Information
              </div>
            </th>
          </tr>
          <tr>
            <td>
              <div class="buyer-info__name">
                Name: <?php echo $name; ?>
              </div>
            </td>
          </tr>
          <tr>
            <td>
              <div class="buyer-info__email">
                Email: <?php echo $email; ?>
              </div>
            </td>
          </tr>
        </table>

        <table class="table totals">
          <tr>
            <th colspan="3" class="table__header">
              <div class="table__heading">
                Totals
              </div>
            </th>
          </tr>
          <tr>
            <td>
              Subtotal: <?php echo $subtotal_html; ?>
            </td>
          </tr>
          <tr>
            <td>
              Shipping: <?php echo $shipping_html; ?>
            </td>
          </tr>
          <tr>
            <td>
              Tax: <?php echo $tax_html; ?>
            </td>
          </tr>
          <tr>
            <td>
              <div class="total-field">
                Total: <?php echo $total_html; ?>
              </div>
            </td>
          </tr>
        </table>
      </div>

      <table class="table currencies">
        <tr>
          <th colspan="3" class="table__header currencies__header">
            <div class="table__heading">
              Choose your currency
            </div>
          </th>
        </tr>
        <tr>
          <?php $i = 0;
									foreach ($currencies_html as $curr) {	?>
        <td>
          <div class="currency <?php echo $curr["tag"] ?>" data-currency="<?php echo $curr["curr"] ?>"
            data-amount="<?php echo $curr["estimate"]; ?>"><?php echo $curr["estimate_str"]; ?></div>

        </td>

        <?php	$i++;
									if($i % 3 == 0) { ?>
        </tr>
        <tr>
          <?php } ?>
          <?php } ?>
        </tr>
        <tr></tr>
        <tr>
          <td colspan="3">
            <div class="payment-error">
              Some error
            </div>
          </td>
        </tr>
        <tr>
          <td colspan="3">
            <button id="complete" class="button">
              Complete checkout
            </button>
          </td>
        </tr>
      </table>
    </div>

    <div class="payment-info">
      <h2 class="payment-info__heading">
        Order#<span class="payment-info__order"></span>
      </h2>
      <p class="payment-info__text">
        To pay, please send exact amount of
        <span class="payment-info__currency">

        </span> to the given addres
      </p>
      <p class="payment-info__amount">

      </p>
      <p class="payment-info__address">

      </p>
      <button class="payment-info__copy">
        Copy
      </button>
      <p class="payment-info__status">

      </p>
      <p class="payment-info__powered-by">
        Powered by NowPayments
      </p>
      <button class="payment-info__paid button">
        I paid
      </button>
    </div>
  </div>
  <b class="loading"></b>
  <script>
  function error(str) {
    var $error = $('.payment-error');
    $error.text(str).show();
  }
  $('.currency').click(function(e) {
    $('.currency').removeClass("tag");
    $(this).addClass("tag");
  });
  var payment_id = 0;
  var pay_address = '';
  $('#complete').click(function(e) {
    $('.loading').show();
    var choose = $('.currency.tag');
    var price_currency = "usd";
    var price_amount = "<?php echo $total; ?>";
    var pay_currency = choose.attr('data-currency');
    var pay_amount = choose.attr('data-amount');
    var post_form = {
      "price_amount": price_amount,
      "price_currency": price_currency,
      "pay_amount": pay_amount,
      "pay_currency": pay_currency,
      "order_id": "<?php echo $invoice; ?>"
    };
    $.ajax({
      type: 'POST',
      url: '<?php echo Api::URL; ?>/payment',
      headers: {
        "x-api-key": "<?php echo $api->getKey(); ?>",
        "Content-Type": "application/json"
      },
      data: JSON.stringify(post_form),
      success: function(data) {
        payment_id = data.payment_id;
        pay_address = data.pay_address;
        $('.payment-checkout').hide();
        $('.payment-info').show();
        $('.payment-error').hide();
        $('.payment-info__currency').text(data.pay_currency);
        $('.payment-info__order').text(<?php echo $order_id;?>);
        $('.payment-info__amount').text(`${data.pay_amount} ${data.pay_currency}`);
        $('.payment-info__address').text(data.pay_address);
        $("#pay").show();
      },
      complete: function() {
        $('.loading').hide();
      },
      error: function(xhr, str) {
        if (xhr.responseText.indexOf("Amount is less then minimal") > 0) {
          error(xhr.responseJSON.errors);
        } else {
          error('Error: ' + xhr.responseCode);
        }
      }
    });
  });

  $(".payment-info__paid").click(function(e) {
    $paymentStatus = $(".payment-info__status");
    $.ajax({
      type: 'GET',
      url: '<?php echo Api::URL; ?>/payment/' + payment_id,
      headers: {
        "x-api-key": "<?php echo $api->getKey(); ?>",
      },
      success: function(data) {
        $paymentStatus.text('Payment status: ' + data.payment_status);
        $paymentStatus.removeClass('payment-info__status_error');
        if (data.payment_status == "success") {
          $(location).attr("href", "<?php echo $success_url;?>");
        }
      },
      error: function(xhr, str) {
        $paymentStatus.text('Error: ' + xhr.responseCode);
        $paymentStatus.addClass('payment-info__status_error');
      }
    });
  });

  function сopyTextToClipboard(text) {
    var textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.setAttribute('readonly', '');
    textArea.style.position = 'absolute';
    textArea.style.left = '-9999px';
    document.body.appendChild(textArea);
    textArea.select();
    try {
      document.execCommand('copy');
      textArea.remove();
    } catch (error) {
      textArea.remove();
      return false;
    }

    return true;
  };

  var $copyBtn = $('.payment-info__copy');
  $copyBtn.click(function() {
    try {
      сopyTextToClipboard(pay_address);
      $copyBtn.addClass('payment-info__copy_success');
      $copyBtn.text('Copied');
      setTimeout(() => {
        $copyBtn.removeClass('payment-info__copy_success');
        $copyBtn.text('Copy');
      }, 2000);
    } catch (error) {
      $copyBtn.addClass('payment-info__copy_error');
      $copyBtn.text('Not Copied');
      setTimeout(() => {
        $copyBtn.removeClass('payment-info__copy_error');
        $copyBtn.text('Copy');
      }, 2000);
    }
  });
  </script>
</body>

</html>
