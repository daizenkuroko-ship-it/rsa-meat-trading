// RSA Meat Trading - application script

const API_BASE = 'api';


/* =========================================================
   HELPERS
========================================================= */

function escapeHTML(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}


async function fetchJSON(url, options = {}) {

  const response = await fetch(url, options);

  const text = await response.text();

  let data;

  try {
    data = JSON.parse(text);
  } catch {
    throw new Error('The server returned an invalid response.');
  }

  if (!response.ok) {
    throw new Error(
      data.message || 'Request failed.'
    );
  }

  return data;
}


function generateOrderNumber() {

  const d = new Date();

  const yr = d.getFullYear();

  const rnd =
    Math.floor(Math.random() * 9000) + 1000;

  return `ORD-${yr}-${rnd}`;
}


/* =========================================================
   LOGIN
========================================================= */

document.addEventListener(
  'DOMContentLoaded',
  function () {

    const loginForm =
      document.getElementById('loginForm');

    if (!loginForm) return;


    loginForm.addEventListener(
      'submit',
      async function (e) {

        e.preventDefault();


        const u =
          document
            .getElementById('username')
            .value
            .trim();


        const p =
          document
            .getElementById('password')
            .value;


        const errorBox =
          document.getElementById('authError');


        try {

          const data =
            await fetchJSON(
              `${API_BASE}/login.php`,
              {
                method: 'POST',

                headers: {
                  'Content-Type':
                    'application/json'
                },

                body: JSON.stringify({
                  username: u,
                  password: p
                })
              }
            );


          if (data.success) {

            window.location.href =
              'dashboard.html';

          } else {

            errorBox.style.display =
              'block';

            errorBox.textContent =
              data.message ||
              'Invalid credentials.';

          }

        } catch (err) {

          errorBox.style.display =
            'block';

          errorBox.innerHTML =
            `<strong>Login error</strong><br>${escapeHTML(
              err.message
            )}`;

        }

      }
    );

  }
);


/* =========================================================
   LOGOUT
========================================================= */

document
  .querySelector('.logout-link')
  ?.addEventListener(
    'click',
    (e) => {

      e.preventDefault();

      window.location.href =
        `${API_BASE}/logout.php`;

    }
  );


/* =========================================================
   DASHBOARD
========================================================= */

if (
  document.getElementById('totalOrders')
) {
  loadDashboard();
}


async function loadDashboard() {

  try {

    const stats =
      await fetchJSON(
        `${API_BASE}/orders.php?action=stats&_=${Date.now()}`
      );


    const totalOrders =
      document.getElementById('totalOrders');

    const pendingCount =
      document.getElementById('pendingCount');

    const preparingCount =
      document.getElementById('preparingCount');

    const readyCount =
      document.getElementById('readyCount');

    const completedCount =
      document.getElementById('completedCount');


    if (totalOrders) {
      totalOrders.textContent =
        stats.total || 0;
    }

    if (pendingCount) {
      pendingCount.textContent =
        stats.pending || 0;
    }

    if (preparingCount) {
      preparingCount.textContent =
        stats.preparing || 0;
    }

    if (readyCount) {
      readyCount.textContent =
        stats.ready || 0;
    }

    if (completedCount) {
      completedCount.textContent =
        stats.completed || 0;
    }


    const bigTotal =
      document.getElementById('bigTotal');

    if (bigTotal) {
      bigTotal.textContent =
        stats.total || 0;
    }


    const compStat =
      document.getElementById('compStat');

    if (compStat) {
      compStat.textContent =
        stats.completed || 0;
    }


    const pendStat =
      document.getElementById('pendStat');

    if (pendStat) {
      pendStat.textContent =
        stats.pending || 0;
    }


    const prepStat =
      document.getElementById('prepStat');

    if (prepStat) {
      prepStat.textContent =
        stats.preparing || 0;
    }


    const readyStat =
      document.getElementById('readyStat');

    if (readyStat) {
      readyStat.textContent =
        stats.ready || 0;
    }


    const orders =
      await fetchJSON(
        `${API_BASE}/orders.php?action=list&search=&status=All&_=${Date.now()}`
      );


    const tbody =
      document.getElementById(
        'recentOrdersBody'
      );


    if (!tbody) return;


    if (!orders.length) {

      tbody.innerHTML = `
        <tr>

          <td
            colspan="6"
            style="
              text-align:center;
              color:#888;
              padding:1.5rem;
            "
          >

            No orders yet.

            <a
              href="create-order.html"
              style="color:#990033;"
            >
              Create one →
            </a>

          </td>

        </tr>
      `;

      return;
    }


    tbody.innerHTML =
      orders
        .slice(0, 5)
        .map(o => `

          <tr>

            <td>
              <strong>
                ${escapeHTML(
                  o.order_number
                )}
              </strong>
            </td>

            <td>
              ${escapeHTML(
                o.company_name
              )}
            </td>

            <td>
              ${escapeHTML(
                o.order_date
              )}
            </td>

            <td>
              ₱${Number.parseFloat(
                o.total_amount || 0
              ).toFixed(2)}
            </td>

            <td>

              <span
                class="status ${escapeHTML(
                  String(
                    o.status || ''
                  ).toLowerCase()
                )}"
              >
                ${escapeHTML(
                  o.status
                )}
              </span>

            </td>

            <td>

              <a
                href="orders.html"
                class="view-link"
              >
                View
              </a>

            </td>

          </tr>

        `)
        .join('');


  } catch (err) {

    const tbody =
      document.getElementById(
        'recentOrdersBody'
      );


    if (tbody) {

      tbody.innerHTML = `

        <tr>

          <td
            colspan="6"
            style="
              text-align:center;
              color:#888;
              padding:1.5rem;
            "
          >

            ${escapeHTML(
              err.message
            )}

          </td>

        </tr>

      `;

    }

  }

}


/* =========================================================
   ORDERS LIST
   COMPACT VERSION
========================================================= */

if (
  document.getElementById('ordersBody')
) {
  loadOrders();
}


document
  .getElementById('searchInput')
  ?.addEventListener(
    'input',
    loadOrders
  );


document
  .getElementById('statusFilter')
  ?.addEventListener(
    'change',
    loadOrders
  );


async function loadOrders() {

  const search =
    document.getElementById(
      'searchInput'
    )?.value || '';


  const status =
    document.getElementById(
      'statusFilter'
    )?.value || 'All';


  const tbody =
    document.getElementById(
      'ordersBody'
    );


  if (!tbody) return;


  try {

    const orders =
      await fetchJSON(
        `${API_BASE}/orders.php?action=list` +
        `&status=${encodeURIComponent(status)}` +
        `&search=${encodeURIComponent(search)}` +
        `&_=${Date.now()}`
      );


    /* =========================
       NO ORDERS
    ========================= */

    if (!orders.length) {

      tbody.innerHTML = `

        <tr>

          <td
            colspan="9"
            class="empty"
          >

            No orders found.

            <a
              href="create-order.html"
              style="color:#e60024;"
            >
              Create New Order →
            </a>

          </td>

        </tr>

      `;

      return;
    }


    /* =========================
       RENDER ORDERS
    ========================= */

    tbody.innerHTML =
      orders
        .map(o => {

          const items =
            Array.isArray(o.items)
              ? o.items
              : [];


          /* =========================
             ITEM NAMES
          ========================= */

          const itemNames =
            items.length

              ? `
                <div class="items-list">

                  ${items
                    .map(item => `

                      <div class="item-name">

                        ${escapeHTML(
                          item.product_name ||
                          'Unnamed Item'
                        )}

                      </div>

                    `)
                    .join('')}

                </div>
              `

              : '-';


          /* =========================
             QUANTITIES
          ========================= */

          const quantities =
            items.length

              ? `
                <div class="items-list">

                  ${items
                    .map(item => `

                      <div>
                        ${escapeHTML(
                          String(
                            item.quantity ??
                            '-'
                          )
                        )}
                      </div>

                    `)
                    .join('')}

                </div>
              `

              : '-';


          /* =========================
             PRICES
          ========================= */

          const prices =
            items.length

              ? `
                <div class="items-list">

                  ${items
                    .map(item => {

                      const price =
                        Number.parseFloat(
                          item.price_per_unit ??
                          item.price ??
                          0
                        ) || 0;


                      return `

                        <div>

                          ₱${price.toFixed(2)}

                        </div>

                      `;

                    })
                    .join('')}

                </div>
              `

              : '-';


          /* =========================
             CUSTOMER DETAILS
          ========================= */

          const customerInfo = `

            <tr class="customer-info-row">

              <td colspan="9">

                <div class="customer-info">

                  <span>

                    <strong>
                      Customer:
                    </strong>

                    ${escapeHTML(
                      o.company_name ||
                      '-'
                    )}

                  </span>


                  <span>

                    <strong>
                      Contact:
                    </strong>

                    ${escapeHTML(
                      o.contact_person ||
                      '-'
                    )}

                  </span>


                  <span>

                    <strong>
                      Phone:
                    </strong>

                    ${escapeHTML(
                      o.phone ||
                      '-'
                    )}

                  </span>


                  <span>

                    <strong>
                      Email:
                    </strong>

                    ${escapeHTML(
                      o.email ||
                      '-'
                    )}

                  </span>


                  <span>

                    <strong>
                      Address:
                    </strong>

                    ${escapeHTML(
                      o.address ||
                      '-'
                    )}

                  </span>

                </div>

              </td>

            </tr>

          `;


          /* =========================
             MAIN ORDER ROW
          ========================= */

          return `

            <tr>

              <!-- ORDER NUMBER -->

              <td>

                <strong>

                  ${escapeHTML(
                    o.order_number ||
                    '-'
                  )}

                </strong>

              </td>


              <!-- ITEM NAME -->

              <td>

                ${itemNames}

              </td>


              <!-- QUANTITY -->

              <td>

                ${quantities}

              </td>


              <!-- PRICE -->

              <td>

                ${prices}

              </td>


              <!-- ORDER DATE -->

              <td>

                ${escapeHTML(
                  o.order_date ||
                  '-'
                )}

              </td>


              <!-- DELIVERY DATE -->

              <td>

                ${escapeHTML(
                  o.delivery_date ||
                  '-'
                )}

              </td>


              <!-- TOTAL -->

              <td>

                ₱${Number.parseFloat(
                  o.total_amount || 0
                ).toFixed(2)}

              </td>


              <!-- STATUS -->

              <td>

                <select
                  class="status-select"
                  data-id="${Number(o.id)}"
                  data-status="${escapeHTML(
                    o.status || ''
                  )}"
                >

                  <option
                    value="Pending"
                    ${
                      o.status ===
                      'Pending'
                        ? 'selected'
                        : ''
                    }
                  >
                    Pending
                  </option>


                  <option
                    value="Preparing"
                    ${
                      o.status ===
                      'Preparing'
                        ? 'selected'
                        : ''
                    }
                  >
                    Preparing
                  </option>


                  <option
                    value="Ready"
                    ${
                      o.status ===
                      'Ready'
                        ? 'selected'
                        : ''
                    }
                  >
                    Ready
                  </option>


                  <option
                    value="Completed"
                    ${
                      o.status ===
                      'Completed'
                        ? 'selected'
                        : ''
                    }
                  >
                    Completed
                  </option>

                </select>

              </td>


              <!-- ACTION -->

              <td>

                <button
                  class="icon-btn delete-btn"
                  data-id="${Number(o.id)}"
                  title="Delete"
                  type="button"
                >
                  🗑
                </button>

              </td>

            </tr>


            <!-- CUSTOMER DETAILS -->

            ${customerInfo}

          `;

        })
        .join('');


    /* =========================
       STATUS UPDATE
    ========================= */

    document
      .querySelectorAll(
        '.status-select'
      )
      .forEach(select => {

        select.addEventListener(
          'change',
          async function (e) {

            try {

              const result =
                await fetchJSON(
                  `${API_BASE}/order-update-status.php`,
                  {
                    method: 'POST',

                    headers: {
                      'Content-Type':
                        'application/json'
                    },

                    body:
                      JSON.stringify({
                        order_id:
                          e.target
                            .dataset
                            .id,

                        status:
                          e.target
                            .value
                      })
                  }
                );


              if (!result.success) {

                throw new Error(
                  result.message ||
                  'Unable to update status.'
                );

              }


              loadOrders();


            } catch (err) {

              alert(
                err.message
              );

              loadOrders();

            }

          }
        );

      });


  } catch (err) {

    tbody.innerHTML = `

      <tr>

        <td
          colspan="9"
          class="empty"
        >

          ${escapeHTML(
            err.message
          )}

        </td>

      </tr>

    `;

  }

}


/* =========================================================
   CREATE ORDER FORM
========================================================= */

const form =
  document.getElementById(
    'orderForm'
  );


if (form) {

  const orderNumber =
    document.getElementById(
      'order_number'
    );


  if (orderNumber) {

    orderNumber.value =
      generateOrderNumber();

  }


  loadProducts().then(addItemRow);


  document
    .getElementById('addRow')
    ?.addEventListener(
      'click',
      addItemRow
    );


  form.addEventListener(
    'submit',
    saveOrder
  );

}


/* =========================================================
   ADD ITEM ROW
========================================================= */

/* =========================================================
   PRODUCT PRICE LIST (prices are set by the owner, server-side)
========================================================= */

let RSA_PRODUCTS = [];


async function loadProducts() {

  try {

    const data =
      await fetchJSON(
        `${API_BASE}/products.php?_=${Date.now()}`,
        { credentials: 'same-origin' }
      );

    RSA_PRODUCTS = data.products || [];

  } catch (err) {

    RSA_PRODUCTS = [];

    alert(
      'Could not load the product list: ' +
      err.message
    );

  }


  if (RSA_PRODUCTS.length === 0) {

    const btn =
      document.getElementById(
        'saveOrderBtn'
      );

    if (btn) btn.disabled = true;

    alert(
      'There are no products yet. ' +
      'Please ask the owner to add products ' +
      'in the Owner Portal (Products & Prices).'
    );

  }

}


function productChanged(select) {

  const row =
    select.closest('tr');

  const opt =
    select.options[
      select.selectedIndex
    ];

  row.querySelector('.price').value =
    Number.parseFloat(
      opt?.dataset?.price || 0
    ).toFixed(2);

  updateRowTotal(select);

}


function addItemRow() {

  const tbody =
    document.getElementById(
      'itemsBody'
    );


  if (!tbody) return;


  const row =
    document.createElement('tr');


  row.className =
    'item-row';


  row.innerHTML = `

    <td>

      <select
        class="product-select"
        required
        onchange="productChanged(this)"
      >
        <option value="">Select product…</option>
        ${RSA_PRODUCTS.map(p => `
          <option
            value="${p.id}"
            data-price="${p.price_per_kg}"
          >${escapeHTML(p.name)}</option>
        `).join('')}
      </select>

    </td>


    <td>

      <input
        type="number"
        class="quantity"
        step="0.01"
        min="0.01"
        required
        oninput="updateRowTotal(this)"
      >

    </td>


    <td>

      <input
        type="number"
        class="price"
        step="0.01"
        value="0.00"
        readonly
        title="Price is set by the owner"
        style="background:#f9f9f9;"
      >

    </td>


    <td>

      <input
        type="text"
        class="subtotal"
        value="0.00"
        readonly
        style="background:#f9f9f9;"
      >

    </td>


    <td>

      <button
        type="button"
        class="remove-btn"
        onclick="removeRow(this)"
      >
        ×
      </button>

    </td>

  `;


  tbody.appendChild(row);

}


/* =========================================================
   REMOVE ITEM ROW
========================================================= */

function removeRow(btn) {

  const rows =
    document.querySelectorAll(
      '.item-row'
    );


  if (rows.length > 1) {

    btn
      .closest('tr')
      .remove();

  }


  updateGrandTotal();

}


/* =========================================================
   UPDATE ITEM TOTAL
========================================================= */

function updateRowTotal(input) {

  const row =
    input.closest('tr');


  const qty =
    Number.parseFloat(
      row.querySelector(
        '.quantity'
      ).value
    ) || 0;


  const price =
    Number.parseFloat(
      row.querySelector(
        '.price'
      ).value
    ) || 0;


  row.querySelector(
    '.subtotal'
  ).value =
    (qty * price).toFixed(2);


  updateGrandTotal();

}


/* =========================================================
   UPDATE GRAND TOTAL
========================================================= */

function updateGrandTotal() {

  let total = 0;


  document
    .querySelectorAll(
      '.subtotal'
    )
    .forEach(s => {

      total +=
        Number.parseFloat(
          s.value
        ) || 0;

    });


  const totalEl =
    document.getElementById(
      'grandTotal'
    );


  if (totalEl) {

    totalEl.textContent =
      '₱' +
      total.toFixed(2);

  }

}


/* =========================================================
   SAVE ORDER
========================================================= */

async function saveOrder(e) {

  e.preventDefault();


  const items = [];


  document
    .querySelectorAll(
      '.item-row'
    )
    .forEach(row => {

      const qty =
        Number.parseFloat(
          row.querySelector(
            '.quantity'
          ).value
        ) || 0;


      const price =
        Number.parseFloat(
          row.querySelector(
            '.price'
          ).value
        ) || 0;


      const productId =
        Number.parseInt(
          row.querySelector(
            '.product-select'
          ).value,
          10
        ) || 0;


      // Only product + quantity are sent. The server looks up the price.
      items.push({

        product_id:
          productId,

        quantity:
          qty,

        subtotal:
          qty * price

      });

    });


  const total =
    items.reduce(
      (sum, i) =>
        sum + i.subtotal,
      0
    );


  const orderData = {

    order_number:
      document
        .getElementById(
          'order_number'
        )
        ?.value
        .trim() || generateOrderNumber(),


    company_name:
      document
        .getElementById(
          'company'
        )
        .value
        .trim(),


    contact_person:
      document
        .getElementById(
          'contact'
        )
        .value
        .trim(),


    phone:
      document
        .getElementById(
          'phone'
        )
        .value
        .trim(),


    email:
      document
        .getElementById(
          'email'
        )
        .value
        .trim(),


    address:
      document
        .getElementById(
          'address'
        )
        .value
        .trim(),


    order_date:
      new Date()
        .toISOString()
        .split('T')[0],


    delivery_date:
      document
        .getElementById(
          'delivDate'
        )
        .value,


    status:
      document
        .getElementById(
          'status'
        )
        .value,


    total_amount:
      total,


    items:
      items

  };


  try {

    const result =
      await fetchJSON(
        `${API_BASE}/order-save.php`,
        {
          method: 'POST',

          headers: {
            'Content-Type':
              'application/json'
          },

          body:
            JSON.stringify(
              orderData
            )
        }
      );


    if (!result.success) {

      throw new Error(
        result.message ||
        'Unable to save order.'
      );

    }


    alert(
      'Order saved successfully!'
    );


    window.location.href =
      'orders.html';


  } catch (err) {

    alert(
      'Error: ' +
      err.message
    );

  }

}


/* =========================================================
   CUSTOMERS
========================================================= */

const custSearch =
  document.getElementById(
    'customerSearch'
  );


if (custSearch) {

  custSearch.addEventListener(
    'input',
    () =>
      renderCustomers(
        custSearch.value
      )
  );


  renderCustomers();

}


async function renderCustomers(
  searchTerm = ''
) {

  const tbody =
    document.getElementById(
      'customersBody'
    );


  if (!tbody) return;


  try {

    const orders =
      await fetchJSON(
        `${API_BASE}/reports-data.php`
      );


    const map = {};


    orders.forEach(o => {

      const key =
        String(
          o.company_name || ''
        )
          .toLowerCase()
          .trim();


      if (!map[key]) {

        map[key] = {

          company:
            o.company_name,

          contact:
            o.contact_person ||
            '-',

          phone:
            o.phone ||
            '-',

          email:
            o.email ||
            '-',

          address:
            o.address ||
            '-',

          orderCount:
            0

        };

      }


      map[key].orderCount++;

    });


    let customers =
      Object.values(map);


    if (searchTerm) {

      const t =
        searchTerm.toLowerCase();


      customers =
        customers.filter(c =>

          String(c.company)
            .toLowerCase()
            .includes(t) ||

          String(c.contact)
            .toLowerCase()
            .includes(t) ||

          String(c.phone)
            .includes(t)

        );

    }


    if (!customers.length) {

      tbody.innerHTML = `

        <tr>

          <td
            colspan="6"
            style="
              text-align:center;
              color:#888;
              padding:2rem;
            "
          >

            No customer records found.

            Create orders to see
            customers here.

          </td>

        </tr>

      `;

      return;

    }


    tbody.innerHTML =
      customers
        .map(c => `

          <tr>

            <td>

              <strong>

                ${escapeHTML(
                  c.company
                )}

              </strong>

            </td>


            <td>

              ${escapeHTML(
                c.contact
              )}

            </td>


            <td>

              ${escapeHTML(
                c.phone
              )}

            </td>


            <td>

              ${escapeHTML(
                c.email
              )}

            </td>


            <td>

              ${escapeHTML(
                c.address
              )}

            </td>


            <td>

              ${Number(
                c.orderCount
              )}

            </td>

          </tr>

        `)
        .join('');


  } catch (err) {

    tbody.innerHTML = `

      <tr>

        <td
          colspan="6"
          style="
            text-align:center;
            color:#888;
            padding:2rem;
          "
        >

          ${escapeHTML(
            err.message
          )}

        </td>

      </tr>

    `;

  }

}


/* =========================================================
   REPORTS
========================================================= */

if (
  document.getElementById(
    'repTotalOrders'
  )
) {

  document.addEventListener(
    'DOMContentLoaded',
    generateReports
  );

}


async function generateReports() {

  try {

    const printDate =
      document.getElementById(
        'printDate'
      );


    if (printDate) {

      printDate.textContent =
        new Date().toLocaleString(
          'en-US',
          {
            dateStyle:
              'full',

            timeStyle:
              'short'
          }
        );

    }


    const orders =
      await fetchJSON(
        `${API_BASE}/reports-data.php`
      );


    /* =========================
       NO REPORT DATA
    ========================= */

    if (!orders.length) {

      [
        'repTotalOrders',
        'repTotalRevenue',
        'repCustomers',
        'repCompleted',
        'repActive'
      ].forEach(id => {

        const el =
          document.getElementById(
            id
          );


        if (el) {

          el.textContent =
            id ===
            'repTotalRevenue'

              ? '₱0.00'

              : '0';

        }

      });


      return;

    }


    /* =========================
       TOTALS
    ========================= */

    const totalOrders =
      orders.length;


    const totalRevenue =
      orders.reduce(
        (s, o) =>
          s +
          Number.parseFloat(
            o.total_amount || 0
          ),
        0
      );


    const completed =
      orders.filter(
        o =>
          o.status ===
          'Completed'
      ).length;


    const active =
      totalOrders -
      completed;


    /* =========================
       CUSTOMER MAP
    ========================= */

    const custMap = {};


    orders.forEach(o => {

      const key =
        String(
          o.company_name || ''
        )
          .toLowerCase()
          .trim();


      if (!custMap[key]) {

        custMap[key] = {

          name:
            o.company_name,

          orders:
            0,

          total:
            0

        };

      }


      custMap[key].orders++;


      custMap[key].total +=
        Number.parseFloat(
          o.total_amount || 0
        );

    });


    /* =========================
       SET TEXT HELPER
    ========================= */

    const setText =
      (id, value) => {

        const el =
          document.getElementById(
            id
          );


        if (el) {

          el.textContent =
            value;

        }

      };


    setText(
      'repTotalOrders',
      totalOrders
    );


    setText(
      'repTotalRevenue',
      '₱' +
        totalRevenue.toFixed(2)
    );


    setText(
      'repCustomers',
      Object.keys(
        custMap
      ).length
    );


    setText(
      'repCompleted',
      completed
    );


    setText(
      'repActive',
      active
    );


    /* =========================
       STATUS BREAKDOWN
    ========================= */

    const counts = {

      Pending:
        0,

      Preparing:
        0,

      Ready:
        0,

      Completed:
        0

    };


    orders.forEach(o => {

      if (
        counts[o.status] !==
        undefined
      ) {

        counts[o.status]++;

      }

    });


    const statusBody =
      document.getElementById(
        'statusBreakdown'
      );


    if (statusBody) {

      statusBody.innerHTML =
        Object.entries(
          counts
        )
          .map(
            ([s, c]) => {

              const pct =
                totalOrders > 0

                  ? (
                      (
                        c /
                        totalOrders
                      ) *
                      100
                    ).toFixed(1)

                  : '0.0';


              return `

                <tr>

                  <td>

                    <span
                      class="status ${s.toLowerCase()}"
                    >

                      ${escapeHTML(
                        s
                      )}

                    </span>

                  </td>


                  <td>

                    ${c}

                  </td>


                  <td>

                    ${pct}%

                  </td>

                </tr>

              `;

            }
          )
          .join('');

    }


    /* =========================
       TOP CUSTOMERS
    ========================= */

    const topCust =
      Object.values(
        custMap
      )
        .sort(
          (a, b) =>
            b.orders -
            a.orders
        )
        .slice(
          0,
          5
        );


    const topBody =
      document
        .getElementById(
          'topCustomers'
        )
        ?.closest('table')
        ?.querySelector(
          'tbody'
        );


    if (topBody) {

      topBody.innerHTML =
        topCust.length

          ? topCust
              .map(
                c => `

                  <tr>

                    <td>

                      <strong>

                        ${escapeHTML(
                          c.name
                        )}

                      </strong>

                    </td>


                    <td>

                      ${c.orders}

                    </td>


                    <td>

                      ₱${c.total.toFixed(
                        2
                      )}

                    </td>

                  </tr>

                `
              )
              .join('')

          : `

              <tr>

                <td
                  colspan="3"
                  style="color:#888;"
                >

                  No data

                </td>

              </tr>

            `;

    }


    /* =========================
       PRODUCT SALES
    ========================= */

    const prodMap = {};


    orders.forEach(o =>

      (
        Array.isArray(o.items)
          ? o.items
          : []
      )
        .forEach(i => {

          const name =
            String(
              i.product_name ||
              ''
            );


          if (!prodMap[name]) {

            prodMap[name] = {

              qty:
                0,

              total:
                0

            };

          }


          prodMap[name].qty +=
            Number.parseFloat(
              i.quantity || 0
            );


          prodMap[name].total +=
            Number.parseFloat(
              i.subtotal || 0
            );

        })

    );


    const prodBody =
      document.getElementById(
        'productSales'
      );


    if (prodBody) {

      const productRows =
        Object.entries(
          prodMap
        )
          .sort(
            (a, b) =>
              b[1].total -
              a[1].total
          )
          .map(
            ([name, data]) => `

              <tr>

                <td>

                  ${escapeHTML(
                    name
                  )}

                </td>


                <td>

                  ${data.qty.toFixed(
                    2
                  )}

                </td>


                <td>

                  ₱${data.total.toFixed(
                    2
                  )}

                </td>

              </tr>

            `
          )
          .join('');


      prodBody.innerHTML =
        productRows ||

        `

          <tr>

            <td
              colspan="3"
              style="color:#888;"
            >

              No sales data

            </td>

          </tr>

        `;

    }


    /* =========================
       MONTHLY SUMMARY
    ========================= */

    const monthMap = {};


    orders.forEach(o => {

      const m =
        new Date(
          o.order_date +
          'T00:00:00'
        ).toLocaleDateString(
          'en-US',
          {
            year:
              'numeric',

            month:
              'long'
          }
        );


      if (!monthMap[m]) {

        monthMap[m] = {

          count:
            0,

          total:
            0

        };

      }


      monthMap[m].count++;


      monthMap[m].total +=
        Number.parseFloat(
          o.total_amount || 0
        );

    });


    const monthBody =
      document.getElementById(
        'monthlySummary'
      );


    if (monthBody) {

      const monthRows =
        Object.entries(
          monthMap
        )
          .map(
            ([m, d]) => `

              <tr>

                <td>

                  ${escapeHTML(
                    m
                  )}

                </td>


                <td>

                  ${d.count}

                </td>


                <td>

                  ₱${d.total.toFixed(
                    2
                  )}

                </td>

              </tr>

            `
          )
          .join('');


      monthBody.innerHTML =
        monthRows ||

        `

          <tr>

            <td
              colspan="3"
              style="color:#888;"
            >

              No data

            </td>

          </tr>

        `;

    }


  } catch (err) {

    const target =
      document.getElementById(
        'statusBreakdown'
      );


    if (target) {

      target.innerHTML = `

        <tr>

          <td
            colspan="3"
            style="color:#888;"
          >

            ${escapeHTML(
              err.message
            )}

          </td>

        </tr>

      `;

    }

  }

}