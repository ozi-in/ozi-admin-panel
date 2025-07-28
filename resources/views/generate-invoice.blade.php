<!DOCTYPE html>
<html lang="{{ \App\CentralLogics\Helpers::system_default_language() }}" dir="{{ \App\CentralLogics\Helpers::system_default_direction() }}">
<head>
<meta charset="UTF-8">
<title>Tax Invoice</title>
<style>
@media print {
    .print-btn {
        display: none !important;
    }
  body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    body {
        margin: 0;
        padding: 0;
    }

    .invoice-box {
        width: 100% !important;
        padding: 0;
           border: 1px solid #000 !important;
        box-sizing: border-box;
    }

    .totals {
        width: 100%;
        padding-right: 30px;
        box-sizing: border-box;
        text-align: right;
    }

    table {
        page-break-inside: auto;
    }

    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }

    .section-box, .header {
        width: 100% !important;
        display: block !important;
        box-sizing: border-box;
    }
}

/* General Styling */
body {
    font-family: 'Arial', 'Helvetica', sans-serif;
    color: #000;
    margin: 0;
    padding: 20px;
}

.invoice-box {
    border: 1px solid #000;
    padding: 20px;
    width: 80%;
    margin: 0 auto;
}

.header,
.section {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    margin-bottom: 10px;
}

.section-box {
    border: 1px solid #000;
    padding: 10px;
    width: 48%;
    box-sizing: border-box;
}

h2, h3, h4 {
    margin: 5px 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    font-size: 14px;
}

th, td {
    border: 1px solid #000;
    padding: 6px;
    text-align: center;
}

.totals {
    margin-top: 20px;
    width: 100%;
    text-align: right;
    padding-right: 25px;
    box-sizing: border-box;
}

.grand-total {
    font-size: 18px;
    font-weight: bold;
    margin-top: 10px;
}

.signature {
    text-align: right;
    margin-top: 40px;
}

.signature img {
    width: 80px;
}

.print-btn {
    margin-top: 30px;
    padding: 10px 20px;
    cursor: pointer;
    font-size: 16px;
}

.smallfont {
    font-size: 12px;
}
</style>

</head>
<body>

<div class="invoice-box">
<h2 style="text-align:center;">Tax Invoice</h2>

<div class="header">
<div class="section-box">
<strong>Sold By: {{ $order->store->name }}</strong><br/>
<p class="smallfont" ><span style="font-style: italic;" ><strong>Ship-from Address:</strong></span> {{ $order->store->address }}</p>

</div>

<div class="section-box" style="text-align:right;">


<img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={{ urlencode($invoiceUrl) }}" alt="QR Code">

</div>
</div>

<div class="section">
<div class="section-box" style="gap: 110px;
    display: flex
;">
    <div style="width:50%;">
      <p><strong>Invoice Number:</strong> {{ 'INV-' . str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</p>
  <p>  <strong>Order ID:</strong> {{ $order->id }}</p>
   <p> <strong>Order Date:</strong> {{ date('d-m-Y', strtotime($order->created_at)) }}</p>
    <p>    <strong>Invoice Date:</strong> {{ date('d-m-Y') }}</p>

</div>

<div class="">
    <p><strong>Delivery Address:</strong></p>
@php($address = json_decode($order->delivery_address, true))
{{ $address['contact_person_name'] ?? ($order->customer?->f_name . ' ' . $order->customer?->l_name) }}<br>
{{ $address['address'] ?? '' }}<br>
Phone: {{ $address['contact_person_number'] ?? 'N/A' }}
</div>
</div>
</div>

<table style="border-collapse: collapse;">
<thead>
<tr>
<th>Description</th>
<th>Qty</th>
<th>Gross ₹</th>


<th>Taxable Value ₹</th>
<th>Discount ₹</th>
<th>Total ₹</th>
</tr>
</thead>
<tbody>
<?php
$subtotal = 0;
$sub_total = 0;
$total_subtotal=0;
//$total_tax = 0;
$total_quantity=0;
$total_discount_on_product = 0;
$total_addon_price = 0;
?>
@foreach ($order->details as $key => $details)

<?php

$total=0;
$gross = $details['price'] * $details->quantity;
//  $item_details = json_decode($details->item_details, true);
//  $total_taxable =0;                      
$item = $details->item;
$gross = $details->price * $details->quantity;
$discount = $item->discount_type === 'percent' ? ($gross * $item->discount/ 100) : $item->discount;
$taxable = $gross - $discount;
// $igst = ($taxable * 5) / 100;
//  $line_total = $taxable + $igst;

// $total += $line_total;
//$total_tax += $details->tax_amount;


?>
<tr>
<td>{{ \Illuminate\Support\Str::limit($item['name'] ?? 'Item', 40, '...') }}
<br>
@if (count(json_decode($details['variation'], true)) > 0)
<span style="font-size: 12px;">
{{ translate('messages.variation') }} :
@foreach(json_decode($details['variation'],true) as  $variation)
@if ( isset($variation['name'])  && isset($variation['values']))
<span class="d-block text-capitalize">
<strong>{{  $variation['name']}} - </strong>
@foreach ($variation['values'] as $value)
{{ $value['label']}}
@if ($value !== end($variation['values']))
,
@endif
@endforeach
</span>
@else
@if (isset(json_decode($details['variation'],true)[0]))
@foreach(json_decode($details['variation'],true)[0] as $key1 =>$variation)
<div class="font-size-sm text-body">
<span>{{$key1}} :  </span>
<span class="font-weight-bold">{{$variation}}</span>
</div>
@endforeach
@endif
@endif
@endforeach
</span>
@endif
@foreach (json_decode($details['add_ons'], true) as $key2 => $addon)
@if ($key2 == 0)
<br><span style="font-size: 12px;"><u>{{ translate('messages.addons') }}
</u></span>
@endif
<div style="font-size: 12px;">
<span>{{ Str::limit($addon['name'], 20, '...') }} : </span>
<span class="font-weight-bold">
{{ $addon['quantity'] }} x
{{ \App\CentralLogics\Helpers::format_currency($addon['price']) }}
</span>
</div>
@php($total_addon_price += $addon['price'] * $addon['quantity'])
@endforeach
<?php    

$total=$gross-$discount;
$total_quantity+=$details->quantity ;
$total_subtotal+=$total;
?>
</td>
<td>{{ $details->quantity }}</td>
<td>{{ number_format($gross) }}</td>
<td>{{ number_format($gross) }}</td>
<td>{{ number_format($discount * $details->quantity) }}</td>


<td>{{ number_format($total, 2) }}</td>
</tr>


<?php

$sub_total += $details['price'] * $details['quantity'];
//$total_tax += $details['tax'];z
$total_discount_on_product += ($discount * $details['quantity']);


?>
@endforeach
</tbody>
<tfoot style="font-weight:bold; background-color: #f0f0f0; border-top: 2px solid #000;">
<tr>
    <td colspan="1">Total</td>
    <td>{{$total_quantity}}</td>
    <td>{{ number_format($sub_total) }}</td>
    <td>{{ number_format($sub_total) }}</td>
    <td>-{{ number_format($total_discount_on_product) }}</td>
    <td>{{ number_format($total_subtotal-$total_discount_on_product) }}</td>
</tr>
</tfoot>
</table>

<div class="totals" >

@if($total_addon_price > 0)
<p><strong>Add-on Price:</strong> {{ \App\CentralLogics\Helpers::format_currency($total_addon_price, 2) }}</p>
@endif
<!-- <p><strong>Total Tax:</strong> </p> -->
<?php $delivery_man_tips =$order->dm_tips; ?>

@if($order['dm_tips']>0)

<p><strong>Delivery Man Tips:</strong> {{ \App\CentralLogics\Helpers::format_currency($delivery_man_tips)}} </p>
@endif
@php($additional_charge = $order['additional_charge'])
@if($additional_charge>0)

<p><strong>{{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name')??translate('messages.additional_charge') }}:</strong>


{{ \App\CentralLogics\Helpers::format_currency($additional_charge) }}
</p>
@endif
@if($order['delivery_charge']>0)
@php($del_c = $order['delivery_charge'])
<p><strong>Delivery Charges:</strong> {{ \App\CentralLogics\Helpers::format_currency($del_c)}} </p>
@endif

@if ($order['extra_packaging_amount'] > 0)
<p><strong>{{ translate('messages.Extra_Packaging_Amount') }}:

{{ \App\CentralLogics\Helpers::format_currency($order['extra_packaging_amount']) }}
</strong></p>
@endif

@if($order['coupon_discount_amount'])
<p><strong>{{ translate('messages.coupon_discount') }}
                 
                 
                                {{ \App\CentralLogics\Helpers::format_currency($order['coupon_discount_amount']) }}
                  :</strong></p>
                  @endif
                            @if ($order['ref_bonus_amount'] > 0)
                          <p><strong>{{ translate('messages.Referral_Discount') }}:
                    
                   
                                {{ \App\CentralLogics\Helpers::format_currency($order['ref_bonus_amount']) }}

                             
                             </strong></p>
                            @endif
<p class="grand-total">Grand Total: {{ \App\CentralLogics\Helpers::format_currency($order->order_amount, 2) }}</p>




</div>

<!-- <div class="signature">
<p>{{ $order->store->name }}</p>
<img src="{{ asset('public/assets/admin/img/signature.png') }}" alt="Signature">
<p>Authorized Signatory</p>
</div> -->

<div style="text-align:center;">
<button onclick="window.print()" class="print-btn">🖨 Print</button>
</div>
</div>

</body>
</html>
