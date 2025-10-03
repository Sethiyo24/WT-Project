<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$username = htmlspecialchars($_SESSION['username']);
?>
<!DOCTYPE html>
<html ng-app="POSApp">
<head>
  <meta charset="utf-8">
  <title>POS Dashboard</title>
  <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.8.2/angular.min.js"></script>
  <style>
    /* keep your existing styles (same as before) */
    * { box-sizing: border-box; font-family: Arial, sans-serif; }
    body { margin:0; background:#f4f6f9; background-color: #A7D9F5; }
    header { background:#2c3e50; color:#fff; padding:12px 20px; display:flex; align-items:center; justify-content:space-between; }
    header .brand { font-size:20px; font-weight:700; }
    header .user { font-size:14px; opacity:0.95; }
    nav { background:#34495e; color:#fff; padding:8px 20px; }
    nav a { color:#fff; margin-right:16px; text-decoration:none; font-weight:600; }
    main { display:flex; height: calc(100vh - 92px); padding:16px; gap:16px; }
    .left { flex: 0 0 70%; background:#fff; padding:12px; border-radius:8px; overflow:auto; }
    .right { flex: 0 0 30%; background:#fff; padding:12px; border-radius:8px; overflow:auto; display:flex; flex-direction:column; }
    .controls { margin-bottom:10px; display:flex; gap:10px; }
    .search { flex:1; padding:8px; border-radius:6px; border:1px solid #ddd; }
    .product-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap:12px; }
    .product-card { border:1px solid #eee; border-radius:8px; padding:8px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,0.03); cursor:pointer; }
    .product-card img { width:100%; height:90px; object-fit:cover; border-radius:6px; }
    .product-name { font-weight:600; margin:8px 0 4px; font-size:14px; }
    .product-meta { font-size:13px; color:#555; }
    .add-btn { margin-top:8px; background:#27ae60; color:#fff; border:none; padding:8px 10px; border-radius:6px; cursor:pointer; }
    .add-btn[disabled] { background:#ccc; cursor:not-allowed; }
    .cart { flex:1; display:flex; flex-direction:column; gap:8px; }
    .cart-list { flex:1; overflow:auto; }
    .cart-item { display:flex; justify-content:space-between; align-items:center; padding:8px; border-bottom:1px solid #f0f0f0; }
    .qty-controls button { padding:4px 8px; margin:0 4px; }
    .totals { border-top:1px solid #eee; padding-top:10px; }
    .checkout-btn { background:#2575fc; color:#fff; border:none; padding:12px; border-radius:8px; width:100%; cursor:pointer; font-weight:700; }
    .small { font-size:13px; color:#666; }
  </style>
</head>
<body ng-controller="POSCtrl">

  <header>
    <div class="brand">Psychoney POS</div>
    <div class="user">Logged in as: <?php echo $username; ?> &nbsp; | &nbsp;
      <button style="background-color:#A7D9F5;"><a href="logout.php" style="color:black;text-decoration:none;">Logout</a></button>
    </div>
  </header>

  <nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="inventory.php">Inventory</a>
    <a href="performance.php">Performance</a>
    <a href="record_sale.php">Records</a>
  </nav>

  <main>
    <div class="left">
      <div class="controls">
        <input class="search" type="text" ng-model="q" placeholder="Search products by name...">
        <select ng-model="sortBy">
          <option value="">Sort</option>
          <option value="name">Name A→Z</option>
          <option value="-price">Price high → low</option>
        </select>
      </div>

      <div class="product-grid">
        <div class="product-card" ng-repeat="p in products | filter:q | orderBy:sortBy" title="{{p.name}}">
          <img ng-src="{{p.image && p.image !== '' ? p.image : 'https://via.placeholder.com/160x90?text=No+Image'}}" alt="img">
          <div class="product-name">{{p.name}}</div>
          <div class="product-meta">₹{{p.price | number:2}}  •  {{p.tax_percent}}% GST</div>
          <div class="small" ng-if="p.stock > 5">Stock: {{p.stock}}</div>
          <div class="small" ng-if="p.stock <= 5 && p.stock > 0" style="color:#e67e22">Low stock: {{p.stock}}</div>
          <div class="small" ng-if="p.stock == 0" style="color:#c0392b">Out of stock</div>
          <button class="add-btn" ng-click="addToCart(p)" ng-disabled="p.stock == 0">Add</button>
        </div>
      </div>
    </div>

    <div class="right">
      <h3>Cart</h3>
      <div class="cart">
        <div class="cart-list">
          <div class="cart-item" ng-repeat="(idx, item) in cart">
            <div style="flex:1">
              <div style="font-weight:700">{{item.name}}</div>
              <div class="small">₹{{item.price | number:2}} × {{item.qty}} = ₹{{(item.price*item.qty) | number:2}}</div>
            </div>
            <div style="text-align:right">
              <div class="qty-controls">
                <button ng-click="changeQty(item, -1)">−</button>
                <span>{{item.qty}}</span>
                <button ng-click="changeQty(item, +1)">+</button>
              </div>
              <div style="margin-top:6px; font-weight:700">₹{{(item.price*item.qty + item.tax_amt) | number:2}}</div>
              <button style="margin-top:6px;background:#e74c3c;color:#fff;border:none;padding:6px;border-radius:6px;cursor:pointer;"
                      ng-click="removeItem(idx)">Remove</button>
            </div>
          </div>
          <div ng-if="cart.length == 0" class="small" style="padding:10px;">Cart is empty. Click items on the left to add.</div>
        </div>

        <div class="totals">
          <div class="small">Subtotal: ₹{{total | number:2}}</div>
          <div class="small">Tax: ₹{{tax_total | number:2}}</div>
          <div style="font-weight:800; font-size:18px; margin-top:6px;">Grand Total: ₹{{grand_total | number:2}}</div>
          <div style="margin-top:8px;">
            <button class="checkout-btn" ng-click="checkout()">Checkout & Save</button>
          </div>
        </div>
      </div>
    </div>

  </main>

<script>
angular.module('POSApp', [])
.controller('POSCtrl', ['$scope', '$http', function($scope, $http) {
  $scope.products = [];
  $scope.cart = [];
  $scope.total = 0;
  $scope.tax_total = 0;
  $scope.grand_total = 0;

  $scope.loadProducts = function() {
    $http.get('get_products.php').then(function(resp) {
      $scope.products = resp.data;
    }, function() {
      alert('Could not load products. Check get_products.php');
    });
  };

  $scope.addToCart = function(p) {
    if (p.stock == 0) { alert('Item out of stock'); return; }
    var found = $scope.cart.find(function(i){ return i.id == p.id; });
    if (found) {
      if (found.qty < p.stock) { found.qty += 1; }
      else { alert('Not enough stock'); }
    } else {
      $scope.cart.push({
        id: p.id,
        name: p.name,
        price: parseFloat(p.price),
        qty: 1,
        tax_percent: parseFloat(p.tax_percent),
        tax_amt: (parseFloat(p.price) * 1) * (parseFloat(p.tax_percent)/100)
      });
    }
    $scope.calculateTotals();
  };

  $scope.changeQty = function(item, delta) {
    var prod = $scope.products.find(function(p){ return p.id == item.id; });
    var newQty = item.qty + delta;
    if (newQty < 1) return;
    if (prod && newQty > prod.stock) { alert('Not enough stock'); return; }
    item.qty = newQty;
    item.tax_amt = (item.price * item.qty) * (item.tax_percent/100);
    $scope.calculateTotals();
  };

  $scope.removeItem = function(idx) {
    $scope.cart.splice(idx, 1);
    $scope.calculateTotals();
  };

  $scope.calculateTotals = function() {
    var total=0, tax_total=0;
    $scope.cart.forEach(function(it) {
      total += it.price * it.qty;
      tax_total += (it.price * it.qty) * (it.tax_percent/100);
    });
    $scope.total = total;
    $scope.tax_total = tax_total;
    $scope.grand_total = total + tax_total;
  };

  $scope.checkout = function() {
    if ($scope.cart.length == 0) { alert('Cart is empty'); return; }

    var items = $scope.cart.map(function(i){ return { id: i.id, qty: i.qty, price: i.price, tax_amt: (i.price*i.qty)*(i.tax_percent/100) }; });

    var data = 'items=' + encodeURIComponent(JSON.stringify(items))
             + '&total=' + encodeURIComponent($scope.total)
             + '&tax_total=' + encodeURIComponent($scope.tax_total)
             + '&grand_total=' + encodeURIComponent($scope.grand_total);

    $http.post('add_sale.php', data, {
      headers: {'Content-Type': 'application/x-www-form-urlencoded'}
    }).then(function(resp) {
      if (resp.data && resp.data.success) {
        alert('Sale recorded. Bill ID: ' + resp.data.sale_id);
        $scope.cart = [];
        $scope.calculateTotals();
        $scope.loadProducts(); // refresh stock after sale
      } else {
        alert('Failed to save sale: ' + (resp.data.error || 'unknown'));
      }
    }, function() {
      alert('Server error while saving sale.');
    });
  };

  // init
  $scope.loadProducts();
  $scope.calculateTotals();
}]);
</script>

</body>
</html>
