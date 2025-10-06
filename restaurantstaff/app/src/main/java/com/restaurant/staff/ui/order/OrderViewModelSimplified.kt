package com.restaurant.staff.ui.order

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.restaurant.staff.data.model.*
import com.restaurant.staff.data.repository.MenuRepository
import com.restaurant.staff.data.repository.OrderRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class OrderViewModelSimplified(
    private val menuRepository: MenuRepository,
    private val orderRepository: OrderRepository
) : ViewModel() {

    // Current table ID
    private var currentTableId: Int = 0

    // Current order
    private val _currentOrder = MutableStateFlow<Order?>(null)
    val currentOrder: StateFlow<Order?> = _currentOrder.asStateFlow()

    // Order items
    private val _orderItems = MutableStateFlow<List<OrderItem>>(emptyList())
    val orderItems: StateFlow<List<OrderItem>> = _orderItems.asStateFlow()

    // Pending items (awaiting approval)
    private val _pendingItems = MutableStateFlow<List<PendingItem>>(emptyList())
    val pendingItems: StateFlow<List<PendingItem>> = _pendingItems.asStateFlow()

    // Menu items for search
    private val _menuItems = MutableStateFlow<List<MenuItem>>(emptyList())
    val menuItems: StateFlow<List<MenuItem>> = _menuItems.asStateFlow()

    // Search query
    private val _searchQuery = MutableStateFlow("")
    val searchQuery: StateFlow<String> = _searchQuery.asStateFlow()

    // Filtered menu items
    private val _filteredMenuItems = MutableStateFlow<List<MenuItem>>(emptyList())
    val filteredMenuItems: StateFlow<List<MenuItem>> = _filteredMenuItems.asStateFlow()

    // UI State
    private val _uiState = MutableStateFlow<Resource<Unit>?>(null)
    val uiState: StateFlow<Resource<Unit>?> = _uiState.asStateFlow()

    // Cart items
    private val _cartItems = MutableStateFlow<List<MenuItem>>(emptyList())
    val cartItems: StateFlow<List<MenuItem>> = _cartItems.asStateFlow()

    // Loading states
    private val _isLoadingOrders = MutableStateFlow(false)
    val isLoadingOrders: StateFlow<Boolean> = _isLoadingOrders.asStateFlow()

    private val _isLoadingMenu = MutableStateFlow(false)
    val isLoadingMenu: StateFlow<Boolean> = _isLoadingMenu.asStateFlow()

    // Prevent double-add
    private val _isAddingItem = MutableStateFlow(false)
    val isAddingItem: StateFlow<Boolean> = _isAddingItem.asStateFlow()

    // Order state
    private val _orderState = MutableStateFlow<Resource<Unit>?>(null)
    val orderState: StateFlow<Resource<Unit>?> = _orderState.asStateFlow()

    // Existing order items (alias for orderItems)
    val existingOrderItems: StateFlow<List<OrderItem>> = _orderItems.asStateFlow()

    private var pollingJob: kotlinx.coroutines.Job? = null

    fun setCurrentTable(tableId: Int) {
        currentTableId = tableId
        loadCurrentOrder()
        startPolling()
    }

    fun stopPolling() {
        pollingJob?.cancel()
        pollingJob = null
    }

    private fun startPolling() {
        stopPolling()
        pollingJob = viewModelScope.launch {
            while (true) {
                kotlinx.coroutines.delay(5000) // Poll every 5 seconds
                try {
                    android.util.Log.d("OrderViewModelSimplified", "Polling order updates for table $currentTableId")
                    loadCurrentOrder()
                } catch (e: Exception) {
                    android.util.Log.e("OrderViewModelSimplified", "Polling error", e)
                }
            }
        }
    }

    private fun loadCurrentOrder() {
        viewModelScope.launch {
            try {
                _uiState.value = Resource.Loading()

                // Sync current order for table from API (fallback to local)
                val payload = orderRepository.syncCurrentOrderByTable(currentTableId)
                val order = payload?.order
                _currentOrder.value = order

                if (order != null) {
                    // Prefer fresh items from payload; fallback to local cache
                    val payloadItems = order.items
                    android.util.Log.d("OrderViewModelSimplified", "Payload items: ${payloadItems?.size ?: 0}, isEmpty=${payloadItems?.isEmpty()}, isNull=${payloadItems == null}")
                    if (payloadItems != null && payloadItems.isNotEmpty()) {
                        _orderItems.value = payloadItems
                        android.util.Log.d("OrderViewModelSimplified", "Loaded ${payloadItems.size} order items from payload")
                    } else {
                        val items = orderRepository.getOrderItems(order.id)
                        _orderItems.value = items
                        android.util.Log.d("OrderViewModelSimplified", "Loaded ${items.size} order items from cache (fallback)")
                    }
                    // Bind pending items from payload
                    _pendingItems.value = order.pendingItems ?: emptyList()
                    android.util.Log.d("OrderViewModelSimplified", "Loaded ${order.pendingItems?.size ?: 0} pending items")
                } else {
                    _orderItems.value = emptyList()
                    _pendingItems.value = emptyList()
                    android.util.Log.d("OrderViewModelSimplified", "No order found for table $currentTableId")
                }

                _uiState.value = Resource.Success(Unit)
            } catch (e: Exception) {
                android.util.Log.e("OrderViewModelSimplified", "Failed to load order", e)
                _uiState.value = Resource.Error(e.message ?: "Failed to load order")
            }
        }
    }

    fun loadMenuItems() {
        viewModelScope.launch {
            try {
                android.util.Log.d("OrderViewModelSimplified", "Loading menu items")
                _uiState.value = Resource.Loading()

                val result = menuRepository.getAllMenuItems()
                when (result) {
                    is NetworkResult.Success -> {
                        android.util.Log.d("OrderViewModelSimplified", "Loaded ${result.data.items.size} menu items")
                        _menuItems.value = result.data.items
                        _filteredMenuItems.value = result.data.items
                        _uiState.value = Resource.Success(Unit)
                    }
                    is NetworkResult.Error -> {
                        android.util.Log.e("OrderViewModelSimplified", "Failed to load menu items: ${result.message}")
                        _uiState.value = Resource.Error(result.message)
                    }
                    is NetworkResult.Loading -> {
                        _uiState.value = Resource.Loading()
                    }
                }
            } catch (e: Exception) {
                android.util.Log.e("OrderViewModelSimplified", "Exception loading menu items", e)
                _uiState.value = Resource.Error(e.message ?: "Failed to load menu items")
            }
        }
    }

    fun searchMenuItems(query: String) {
        _searchQuery.value = query

        if (query.isBlank()) {
            _filteredMenuItems.value = _menuItems.value
        } else {
            val filtered = _menuItems.value.filter { item ->
                item.name.contains(query, ignoreCase = true) ||
                        item.description?.contains(query, ignoreCase = true) == true
            }
            _filteredMenuItems.value = filtered
        }
    }

    fun addToCart(menuItem: MenuItem) {
        val currentCart = _cartItems.value.toMutableList()
        currentCart.add(menuItem)
        _cartItems.value = currentCart
    }

    fun removeFromCart(menuItem: MenuItem) {
        val currentCart = _cartItems.value.toMutableList()
        currentCart.remove(menuItem)
        _cartItems.value = currentCart
    }

    fun clearCart() {
        _cartItems.value = emptyList()
    }

    fun createOrder() {
        viewModelScope.launch {
            try {
                _uiState.value = Resource.Loading()

                val request = CreateOrderRequest(
                    tableId = currentTableId,
                    customerCount = 1, // Default
                    specialRequests = null
                )

                orderRepository.createOrder(request).collect { resource ->
                    when (resource) {
                        is Resource.Success -> {
                            _currentOrder.value = resource.data
                            clearCart()
                            loadCurrentOrder()
                            _uiState.value = Resource.Success(Unit)
                        }
                        is Resource.Error -> {
                            _uiState.value = Resource.Error(resource.message)
                        }
                        is Resource.Loading -> {
                            _uiState.value = Resource.Loading()
                        }
                    }
                }
            } catch (e: Exception) {
                _uiState.value = Resource.Error(e.message ?: "Failed to create order")
            }
        }
    }

    fun addItemToOrder(menuItem: MenuItem, quantity: Int, notes: String? = null) {
        viewModelScope.launch {
            try {
                // Prevent double-tap/double-add
                if (_isAddingItem.value) {
                    android.util.Log.w("OrderViewModelSimplified", "Already adding item, ignoring duplicate call")
                    return@launch
                }
                _isAddingItem.value = true
                _uiState.value = Resource.Loading()

                android.util.Log.d("OrderViewModelSimplified", "Adding item to order: ${menuItem.name}, quantity: $quantity")

                var currentOrder = _currentOrder.value

                // If no current order, create one first
                if (currentOrder == null) {
                    android.util.Log.d("OrderViewModelSimplified", "No current order found, creating new order for table: $currentTableId")

                    val createOrderRequest = CreateOrderRequest(
                        tableId = currentTableId,
                        customerCount = 1, // Default customer count
                        specialRequests = "Order created automatically"
                    )

                    orderRepository.createOrder(createOrderRequest).collect { resource ->
                        when (resource) {
                            is Resource.Success -> {
                                val newOrder = resource.data
                                currentOrder = newOrder
                                _currentOrder.value = newOrder
                                android.util.Log.d("OrderViewModelSimplified", "Created new order: $newOrder")

                                // Now add the item to the new order
                                addItemToExistingOrder(newOrder, menuItem, quantity, notes)
                            }
                            is Resource.Error -> {
                                _uiState.value = Resource.Error("Failed to create order: ${resource.message}")
                            }
                            is Resource.Loading -> {
                                _uiState.value = Resource.Loading()
                            }
                        }
                    }
                } else {
                    // Add item to existing order
                    val existingOrder = currentOrder
                    if (existingOrder != null) {
                        addItemToExistingOrder(existingOrder, menuItem, quantity, notes)
                    } else {
                        _uiState.value = Resource.Error("Order became null unexpectedly")
                    }
                }
            } catch (e: Exception) {
                android.util.Log.e("OrderViewModelSimplified", "Failed to add item to order", e)
                _uiState.value = Resource.Error(e.message ?: "Failed to add item to order")
            } finally {
                _isAddingItem.value = false
            }
        }
    }

    private suspend fun addItemToExistingOrder(order: Order, menuItem: MenuItem, quantity: Int, notes: String?) {
        val request = AddOrderItemRequest(
            productId = menuItem.id,
            quantity = quantity,
            specialInstructions = notes
        )

        android.util.Log.d("OrderViewModelSimplified", "Adding item to existing order: ${order.id}")

        orderRepository.addOrderItem(order.id, request).collect { resource ->
            when (resource) {
                is Resource.Success -> {
                    android.util.Log.d("OrderViewModelSimplified", "Successfully added item to order")
                    loadCurrentOrder() // Reload to get updated items
                    // Update table pending amount so Tables screen shows total
                    val total = calculateOrderTotal()
                    orderRepository.updateTableOrderInfo(currentTableId, order.id, total)
                    _uiState.value = Resource.Success(Unit)
                }
                is Resource.Error -> {
                    android.util.Log.e("OrderViewModelSimplified", "Failed to add item to order: ${resource.message}")
                    _uiState.value = Resource.Error(resource.message)
                }
                is Resource.Loading -> {
                    _uiState.value = Resource.Loading()
                }
            }
        }
    }

    fun submitOrder() {
        viewModelScope.launch {
            try {
                _uiState.value = Resource.Loading()

                // Theo yêu cầu: giữ đơn ở trạng thái pending đến khi admin thanh toán.
                // Do đó không gọi API submit; chỉ reload để cập nhật UI.
                loadCurrentOrder()
                _uiState.value = Resource.Success(Unit)
            } catch (e: Exception) {
                _uiState.value = Resource.Error(e.message ?: "Failed to submit order")
            }
        }
    }

    fun getCartTotal(): Double {
        return _cartItems.value.sumOf { it.price }
    }

    fun getOrderTotal(): Double {
        return _orderItems.value.sumOf { it.totalPrice }
    }

    fun canEditOrder(): Boolean {
        return _currentOrder.value?.canEdit == true
    }

    fun canSubmitOrder(): Boolean {
        return _currentOrder.value != null && _orderItems.value.isNotEmpty()
    }

    // Additional methods needed by OrderFragment
    fun updateOrderItemQuantity(orderItem: OrderItem, quantity: Int) {
        viewModelScope.launch {
            try {
                val currentOrder = _currentOrder.value ?: run {
                    _uiState.value = Resource.Error("No active order found")
                    return@launch
                }

                // Nếu quantity = 0 => coi như xóa món
                if (quantity == 0) {
                    orderRepository.deleteOrderItem(currentOrder.id, orderItem.id).collect { resource ->
                        when (resource) {
                            is Resource.Success -> {
                                loadCurrentOrder()
                                val total = calculateOrderTotal()
                                orderRepository.updateTableOrderInfo(currentTableId, currentOrder.id, total)
                                _uiState.value = Resource.Success(Unit)
                            }
                            is Resource.Error -> {
                                _uiState.value = Resource.Error(resource.message)
                            }
                            is Resource.Loading -> {
                                _uiState.value = Resource.Loading()
                            }
                        }
                    }
                } else {
                    val request = UpdateOrderItemRequest(
                        quantity = quantity,
                        specialInstructions = null
                    )

                    orderRepository.updateOrderItem(currentOrder.id, orderItem.id, request).collect { resource ->
                        when (resource) {
                            is Resource.Success -> {
                                // Reload items to reflect backend-calculated totals
                                loadCurrentOrder()
                                // Update table order info (pending amount)
                                val total = calculateOrderTotal()
                                orderRepository.updateTableOrderInfo(currentTableId, currentOrder.id, total)
                                _uiState.value = Resource.Success(Unit)
                            }
                            is Resource.Error -> {
                                _uiState.value = Resource.Error(resource.message)
                            }
                            is Resource.Loading -> {
                                _uiState.value = Resource.Loading()
                            }
                        }
                    }
                }
            } catch (e: Exception) {
                _uiState.value = Resource.Error(e.message ?: "Failed to update item")
            }
        }
    }

    fun addMenuItemToOrder(menuItem: MenuItem, quantity: Int = 1) {
        addItemToOrder(menuItem, quantity)
    }

    fun removeOrderItem(orderItem: OrderItem) {
        viewModelScope.launch {
            try {
                val currentOrder = _currentOrder.value ?: run {
                    _uiState.value = Resource.Error("No active order found")
                    return@launch
                }

                orderRepository.deleteOrderItem(currentOrder.id, orderItem.id).collect { resource ->
                    when (resource) {
                        is Resource.Success -> {
                            loadCurrentOrder()
                            val total = calculateOrderTotal()
                            orderRepository.updateTableOrderInfo(currentTableId, currentOrder.id, total)
                            _uiState.value = Resource.Success(Unit)
                        }
                        is Resource.Error -> {
                            _uiState.value = Resource.Error(resource.message)
                        }
                        is Resource.Loading -> {
                            _uiState.value = Resource.Loading()
                        }
                    }
                }
            } catch (e: Exception) {
                _uiState.value = Resource.Error(e.message ?: "Failed to remove item")
            }
        }
    }

    fun loadTableData(tableId: Int) {
        setCurrentTable(tableId)
    }

    fun refreshTableData(tableId: Int) {
        setCurrentTable(tableId)
    }

    fun calculateOrderTotal(): Double {
        return _orderItems.value.sumOf { it.totalPrice }
    }
}
