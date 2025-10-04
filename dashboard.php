<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}
$username = htmlspecialchars($_SESSION['username']);

include 'db.php';
date_default_timezone_set('Asia/Kolkata');
$user_id = intval($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html ng-app="POSApp">

<head>
  <meta charset="utf-8">
  <title>POS Dashboard</title>
  <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.8.2/angular.min.js"></script>
  <link rel="stylesheet" href="dashboard.css">
</head>

<body ng-controller="POSCtrl">

  <header>
    <div class="brand">POS System</div>
    <nav>
      <a href="dashboard.php">Dashboard</a>
      <a href="inventory.php">Inventory</a>
      <a href="performance.php">Performance</a>
      <a href="record_sale.php">Records</a>
    </nav>
    <div class="user" id="who">Logged in as: <?php echo $username; ?> &nbsp; | &nbsp;
      <button style="background: linear-gradient(135deg, #764ba2, #667eea);"><a href="logout.php"
          style="color:black;text-decoration:none;">Logout</a></button>
    </div>
  </header>
  <!-- Stats section -->
  <section class="stats">
    <div class="stat-box" id="s1">
        <div class="stat-title">Today’s Sales</div>
        <div class="stat-value">{{ todayStats.total_sales }}</div>
    </div>
    <div class="stat-box" id="s2">
        <div class="stat-title">Cash-In(Revenue)</div>
        <div class="stat-value">₹{{ todayStats.revenue | number:2 }}</div>
    </div>
    <div class="stat-box" id="s3">
        <div class="stat-title">Gross Profit</div>
        <div class="stat-value">₹{{ todayStats.profit | number:2 }}</div> 
    </div>
    <div class="stat-box" id="s4">
        <div class="stat-title">Items Sold</div>
        <div class="stat-value">{{ todayStats.items_sold }}</div>
    </div>
</section>

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
          <img ng-src="{{p.image && p.image !== '' ? p.image : 'https://via.placeholder.com/160x90?text=No+Image'}}"
            alt="img">
          <div class="product-name">{{p.name}}</div>
          <div class="product-meta">₹{{p.price | number:2}} • {{p.tax_percent}}% GST</div>
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
              <button
                style="margin-top:6px;background:#e74c3c;color:#fff;border:none;padding:6px;border-radius:6px;cursor:pointer;"
                ng-click="removeItem(idx)">Remove</button>
            </div>
          </div>
          <div ng-if="cart.length == 0" class="small" style="padding:10px;">Cart is empty. Click items on the left to
            add.</div>
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
.controller('POSCtrl', ['$scope', '$http', function ($scope, $http) {
  $scope.products = [];
  $scope.cart = [];
  $scope.total = 0;
  $scope.tax_total = 0;
  $scope.grand_total = 0;

  // --- Today stats ---
  $scope.todayStats = {
    total_sales: 0,
    revenue: 0,
    profit: 0,
    items_sold: 0
  };

// NEW CODE TO BE USED
$scope.loadTodayStats = function() {
    $http.get('today_stats.php', {
        // Explicitly parse the response data as JSON
        transformResponse: function(data, headersGetter) {
            // Only try to parse if there is data
            if (data) {
                try {
                    return angular.fromJson(data);
                } catch (e) {
                    console.error("JSON Parse Error:", e);
                    return data; // Return original data if parsing fails
                }
            }
            return data;
        }
    }).then(function(resp){
        // Success: response data is now guaranteed to be parsed
        $scope.todayStats = resp.data || $scope.todayStats;
    }, function(error){
        // Error handler: this will now only run if the network request fails
        console.error('Failed to load today\'s stats', error);
    });
};

  // --- Load products ---
  $scope.loadProducts = function () {
    $http.get('get_products.php').then(function (resp) {
        // Map each product to include a numeric cost
        $scope.products = resp.data.map(p => ({
    ...p,
    cost: parseFloat(p.cost_price || 0)
}));

    }, function () {
        alert('Could not load products. Check get_products.php');
    });
};


  // --- Add to cart ---
  $scope.addToCart = function (p) {
    if (p.stock == 0) { alert('Item out of stock'); return; }
    var found = $scope.cart.find(function (i) { return i.id == p.id; });
    if (found) {
      if (found.qty < p.stock) { found.qty += 1; }
      else { alert('Not enough stock'); }
    } else {
      $scope.cart.push({
    id: p.id,
    name: p.name,
    price: parseFloat(p.price),
    cost: parseFloat(p.cost || 0),   // <-- ADD THIS
    qty: 1,
    tax_percent: parseFloat(p.tax_percent),
    tax_amt: parseFloat(p.price) * 1 * (parseFloat(p.tax_percent) / 100)
});

    }
    $scope.calculateTotals();
  };

  // --- Change quantity ---
  $scope.changeQty = function (item, delta) {
    var prod = $scope.products.find(function (p) { return p.id == item.id; });
    var newQty = item.qty + delta;
    if (newQty < 1) return;
    if (prod && newQty > prod.stock) { alert('Not enough stock'); return; }
    item.qty = newQty;
    item.tax_amt = (item.price * item.qty) * (item.tax_percent / 100);
    $scope.calculateTotals();
  };

  // --- Remove item ---
  $scope.removeItem = function (idx) {
    $scope.cart.splice(idx, 1);
    $scope.calculateTotals();
  };

  // --- Calculate totals ---
  $scope.calculateTotals = function () {
    var total = 0, tax_total = 0, grand_total = 0, cost_total = 0;

    $scope.cart.forEach(function (it) {
        var item_total = it.price * it.qty;
        var item_tax = item_total * (it.tax_percent / 100);
        total += item_total;
        tax_total += item_tax;
        grand_total += item_total + item_tax;
        cost_total += it.cost * it.qty;  // use correct cost
    });

    $scope.total = total;
    $scope.tax_total = tax_total;
    $scope.grand_total = grand_total;
    $scope.total_profit = grand_total - tax_total - cost_total;  // correct profit
};



  // --- Checkout ---
  $scope.checkout = function () {
    if ($scope.cart.length == 0) { alert('Cart is empty'); return; }

    var items = $scope.cart.map(function (i) { 
      return { id: i.id, qty: i.qty, price: i.price, tax_amt: (i.price * i.qty) * (i.tax_percent / 100) }; 
    });

    var data = 'items=' + encodeURIComponent(JSON.stringify(items))
      + '&total=' + encodeURIComponent($scope.total)
      + '&tax_total=' + encodeURIComponent($scope.tax_total)
      + '&grand_total=' + encodeURIComponent($scope.grand_total)
      + '&total_profit=' + encodeURIComponent($scope.total_profit)

    $http.post('add_sale.php', data, {
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    }).then(function (resp) {
      if (resp.data && resp.data.success) {
        alert('Sale recorded. Bill ID: ' + resp.data.sale_id);
        $scope.cart = [];
        $scope.calculateTotals();
        $scope.loadProducts();      // refresh stock
        $scope.loadTodayStats();    // reload today stats
      } else {
        alert('Failed to save sale: ' + (resp.data.error || 'unknown'));
      }
    }, function () {
      alert('Server error while saving sale.');
    });
  };

  // --- Init ---
  $scope.loadProducts();
  $scope.calculateTotals();
  $scope.loadTodayStats(); // load today stats on page load
}]);
</script>


</body>

</html>