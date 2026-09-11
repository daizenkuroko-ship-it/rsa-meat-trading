// ===== LOGIN =====
const validUser = { username: 'admin', password: 'admin123' };
document.getElementById('loginForm')?.addEventListener('submit', e => {
  e.preventDefault();
  const u = document.getElementById('username').value.trim();
  const p = document.getElementById('password').value.trim();
  if(u === validUser.username && p === validUser.password || u === 'admin@rsameat.com' && p === 'admin123') {
    localStorage.setItem('loggedIn', 'true');
    window.location.href = 'dashboard.html';
  } else {
    document.getElementById('authError').style.display = 'block';
  }
});

// ===== CHECK LOGIN STATUS =====
function checkAuth() {
  if(!localStorage.getItem('loggedIn')) {
    window.location.href = 'index.html';
  }
}
if(!window.location.pathname.endsWith('index.html')) checkAuth();

// ===== ORDERS STORAGE (LocalStorage) =====
function getOrders() { return JSON.parse(localStorage.getItem('rsaOrders') || '[]'); }
function saveOrders(list) { localStorage.setItem('rsaOrders', JSON.stringify(list)); }

// ===== DASHBOARD STATS =====
function renderDashboard() {
  const orders = getOrders();
  const counts = { Total: orders.length, Pending:0, Preparing:0, Ready:0, Completed:0 };
  orders.forEach(o => counts[o.status]++);
  document.getElementById('totalOrders').textContent = counts.Total;
  document.getElementById('pendingCount').textContent = counts.Pending;
  document.getElementById('preparingCount').textContent = counts.Preparing;
  document.getElementById('readyCount').textContent = counts.Ready;
  document.getElementById('completedCount').textContent = counts.Completed;
  document.getElementById('bigTotal').textContent = counts.Total;
  document.getElementById('compStat').textContent = counts.Completed;
  document.getElementById('pendStat').textContent = counts.Pending;
  document.getElementById('prepStat').textContent = counts.Preparing;
  document.getElementById('readyStat').textContent = counts.Ready;

  const tbody = document.getElementById('recentOrdersBody');
  if(!tbody) return;
  if(orders.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#888; padding:1.5rem;">No orders yet. <a href="create-order.html" style="color:#990033;">Create one →</a></td></tr>';
    return;
  }
  tbody.innerHTML = orders.slice(0,5).map(o => `
    <tr>
      <td>${o.orderNo}</td>
      <td>${o.company}</td>
      <td>${o.date}</td>
      <td>₱${o.total.toFixed(2)}</td>
      <td><span class="status ${o.status.toLowerCase()}">${o.status}</span></td>
      <td><a href="orders.html" class="icon-btn">View</a></td>
    </tr>
  `).join('');
}
if(window.location.pathname.endsWith('dashboard.html')) renderDashboard();

// ===== ORDERS LIST WITH EDITABLE STATUS =====
function renderOrders(filter='All') {
  let orders = getOrders();
  if(filter !== 'All') orders = orders.filter(o => o.status === filter);
  const tbody = document.getElementById('ordersBody');
  if(!tbody) return;

  if(orders.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#888; padding:2rem;">No orders found. <a href="create-order.html" style="color:#990033;">Create New Order →</a></td></tr>';
    return;
  }

  tbody.innerHTML = orders.map(o => `
    <tr data-order-id="${o.id}">
      <td>${o.orderNo}</td>
      <td>${o.company}</td>
      <td>${o.date}</td>
      <td>₱${o.total.toFixed(2)}</td>
      <td>
        <select class="status-select" onchange="updateOrderStatus(${o.id}, this.value)">
          <option value="Pending" ${o.status==='Pending'?'selected':''}>Pending</option>
          <option value="Preparing" ${o.status==='Preparing'?'selected':''}>Preparing</option>
          <option value="Ready" ${o.status==='Ready'?'selected':''}>Ready</option>
          <option value="Completed" ${o.status==='Completed'?'selected':''}>Completed</option>
        </select>
      </td>
      <td>
        <button class="icon-btn" title="Delete Order" onclick="deleteOrder(${o.id})">Delete</button>
      </td>
    </tr>
  `).join('');
}

// ===== UPDATE ORDER STATUS =====
function updateOrderStatus(orderId, newStatus) {
  const orders = getOrders();
  const orderIndex = orders.findIndex(o => o.id === orderId);
  if(orderIndex !== -1) {
    orders[orderIndex].status = newStatus;
    saveOrders(orders);
    alert('Status updated to: ' + newStatus);
    renderOrders(document.getElementById('statusFilter')?.value || 'All');
  }
}

// ===== DELETE ORDER =====
function deleteOrder(orderId) {
  if(confirm('Are you sure you want to delete this order?')) {
    let orders = getOrders();
    orders = orders.filter(o => o.id !== orderId);
    saveOrders(orders);
    alert('Order deleted.');
    renderOrders(document.getElementById('statusFilter')?.value || 'All');
  }
}

// ===== Re-render Orders on Filter Change =====
document.getElementById('statusFilter')?.addEventListener('change', e => renderOrders(e.target.value));
if(window.location.pathname.endsWith('orders.html')) renderOrders();

// ===== CREATE ORDER — Calculations & Save =====
function recalcAll() {
  let subtotal = 0;
  document.querySelectorAll('.item-row').forEach(row => {
    const q = parseFloat(row.querySelector('.qty').value) || 0;
    const p = parseFloat(row.querySelector('.price').value) || 0;
    const st = q * p;
    row.querySelector('.row-subtotal').textContent = st.toFixed(2);
    subtotal += st;
  });
  const tax = subtotal * 0.05;
  const gt = subtotal + tax;
  const stEl = document.getElementById('subtotal'); if(stEl) stEl.textContent = '₱' + subtotal.toFixed(2);
  const txEl = document.getElementById('tax'); if(txEl) txEl.textContent = '₱' + tax.toFixed(2);
  const gtEl = document.getElementById('grandTotal'); if(gtEl) gtEl.textContent = '₱' + gt.toFixed(2);
}

document.getElementById('addRow')?.addEventListener('click', () => {
  const tbody = document.getElementById('itemsBody');
  const row = document.createElement('tr');
  row.className = 'item-row';
  row.innerHTML = `
    <td><input type="text" class="prod-name" placeholder="e.g. Beef Ribeye" required></td>
    <td><input type="number" class="qty" step="0.01" min="0" required></td>
    <td><input type="number" class="price" step="0.01" min="0" required></td>
    <td class="row-subtotal">0.00</td>
    <td><button type="button" class="remove-btn">Remove</button></td>
  `;
  tbody.appendChild(row);
  row.querySelector('.qty').addEventListener('input', recalcAll);
  row.querySelector('.price').addEventListener('input', recalcAll);
  row.querySelector('.remove-btn').addEventListener('click', () => { 
    if(document.querySelectorAll('.item-row').length > 1) { row.remove(); recalcAll(); }
  });
});

document.addEventListener('input', e => {
  if(e.target.classList.contains('qty') || e.target.classList.contains('price')) recalcAll();
});

document.addEventListener('click', e => {
  if(e.target.classList.contains('remove-btn') && document.querySelectorAll('.item-row').length > 1) {
    e.target.closest('.item-row').remove(); recalcAll();
  }
});

// ===== SAVE NEW ORDER =====
document.getElementById('orderForm')?.addEventListener('submit', e => {
  e.preventDefault();
  const orders = getOrders();
  const orderNo = 'ORD-' + new Date().getFullYear() + '-' + String(orders.length + 1).padStart(5,'0');
  let subtotal = 0;
  document.querySelectorAll('.item-row').forEach(row => {
    subtotal += (parseFloat(row.querySelector('.qty').value)||0) * (parseFloat(row.querySelector('.price').value)||0);
  });
  const total = subtotal * 1.05;
  const newOrder = {
    id: Date.now(),
    orderNo,
    company: document.getElementById('company').value.trim(),
    contact: document.getElementById('contact').value.trim(),
    phone: document.getElementById('phone').value.trim(),
    email: document.getElementById('email').value.trim(),
    address: document.getElementById('address').value.trim(),
    date: new Date(document.getElementById('orderDate').value).toLocaleDateString('en-US', {month:'short', day:'2-digit', year:'numeric'}),
    rawDate: document.getElementById('orderDate').value,
    delivDate: document.getElementById('delivDate').value,
    status: document.getElementById('status').value,
    subtotal, tax: subtotal*0.05, total
  };
  orders.unshift(newOrder);
  saveOrders(orders);
  alert('Order saved successfully!');
  window.location.href = 'orders.html';
});
