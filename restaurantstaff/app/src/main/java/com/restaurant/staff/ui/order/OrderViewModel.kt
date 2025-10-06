//package com.restaurant.staff.ui.order
//
//import androidx.lifecycle.ViewModel
//import androidx.lifecycle.viewModelScope
//import com.restaurant.staff.data.model.MenuItem
//import com.restaurant.staff.data.model.Order
//import com.restaurant.staff.data.model.OrderItem
//import com.restaurant.staff.data.model.AddOrderItemRequest
//import com.restaurant.staff.data.model.UpdateOrderItemRequest
//import com.restaurant.staff.data.model.PaginatedResponse
//import com.restaurant.staff.data.repository.MenuRepository
//import com.restaurant.staff.data.repository.OrderRepository
//import com.restaurant.staff.data.model.NetworkResult
//import kotlinx.coroutines.flow.MutableStateFlow
//import kotlinx.coroutines.flow.StateFlow
//import kotlinx.coroutines.flow.asStateFlow
//import kotlinx.coroutines.flow.first
//import kotlinx.coroutines.launch
//import com.restaurant.staff.data.model.Resource
//
//
//class OrderViewModel(
//    private val menuRepository: MenuRepository,
//    private val orderRepository: OrderRepository
//) : ViewModel() {
//
//    // Current table ID
//    private var currentTableId: Int = 0
//
//    // Existing order items for the table
//    private val _existingOrderItems = MutableStateFlow<List<OrderItem>>(emptyList())
//    val existingOrderItems: StateFlow<List<OrderItem>> = _existingOrderItems.asStateFlow()
//
//    // Current order for the table
//    private val _currentOrder = MutableStateFlow<Order?>(null)
//    val currentOrder: StateFlow<Order?> = _currentOrder.asStateFlow()
//
//    // All menu items for search
//    private val _allMenuItems = MutableStateFlow<List<MenuItem>>(emptyList())
//    val allMenuItems: StateFlow<List<MenuItem>> = _allMenuItems.asStateFlow()
//
//    // Filtered menu items for search
//    private val _filteredMenuItems = MutableStateFlow<List<MenuItem>>(emptyList())
//    val filteredMenuItems: StateFlow<List<MenuItem>> = _filteredMenuItems.asStateFlow()
//
//    // Search query
//    private val _searchQuery = MutableStateFlow("")
//    val searchQuery: StateFlow<String> = _searchQuery.asStateFlow()
//
//    // Order state
//    private val _orderState = MutableStateFlow<OrderState>(OrderState.Idle)
//    val orderState: StateFlow<OrderState> = _orderState.asStateFlow()
//
//    // Loading states
//    private val _isLoadingOrders = MutableStateFlow(false)
//    val isLoadingOrders: StateFlow<Boolean> = _isLoadingOrders.asStateFlow()
//
//    private val _isLoadingMenu = MutableStateFlow(false)
//    val isLoadingMenu: StateFlow<Boolean> = _isLoadingMenu.asStateFlow()
//
//    fun loadTableData(tableId: Int) {
//        currentTableId = tableId
//        android.util.Log.d("OrderViewModel", "Loading data for table: $tableId")
//
//        // Load existing orders for this table
//        loadExistingOrders(tableId)
//
//        // Load all menu items for search
//        loadAllMenuItems()
//
//        android.util.Log.d("OrderViewModel", "loadTableData completed for table: $tableId")
//    }
//
//    fun refreshTableData(tableId: Int) {
//        android.util.Log.d("OrderViewModel", "Refreshing data for table: $tableId")
//
//        // Only refresh if it's the same table
//        if (currentTableId == tableId) {
//            loadExistingOrders(tableId)
//        }
//    }
//
//    private fun loadExistingOrders(tableId: Int) {
//        _isLoadingOrders.value = true
//        viewModelScope.launch {
//            try {
//                // First try to get current order from API by checking tables endpoint
//                android.util.Log.d("OrderViewModel", "Loading current order for table $tableId from API")
//
//                // Get table info from API to find current order
//                val tableInfo = orderRepository.getTableInfo(tableId)
//                if (tableInfo != null && tableInfo.currentOrderId != null) {
//                    android.util.Log.d("OrderViewModel", "Found current order ID: ${tableInfo.currentOrderId}")
//
//                    // Load order details from API
//                    orderRepository.getOrderDetails(tableInfo.currentOrderId).collect { resource ->
//                        when (resource) {
//                            is Resource.Success<Order> -> {
//                                val order = resource.data
//                                _currentOrder.value = order
//                                android.util.Log.d("OrderViewModel", "Successfully loaded order ${order.id} from API")
//
//                                val apiItems = order.items ?: emptyList()
//                                android.util.Log.d("OrderViewModel", "API returned ${apiItems.size} items")
//
//                                if (apiItems.isNotEmpty()) {
//                                    android.util.Log.d("OrderViewModel", "Successfully loaded ${apiItems.size} items from API")
//                                    val populatedItems = apiItems.map { orderItem ->
//                                        if (orderItem.itemName.isNullOrBlank() && orderItem.productId != null) {
//                                            val menuItem = _allMenuItems.value.find { it.id == orderItem.productId }
//                                            if (menuItem != null) {
//                                                orderItem.copy(itemName = menuItem.name)
//                                            } else {
//                                                orderItem
//                                            }
//                                        } else {
//                                            orderItem
//                                        }
//                                    }
//                                    _existingOrderItems.value = populatedItems
//
//                                    // Debug: Check item names
//                                    populatedItems.forEach { item ->
//                                        android.util.Log.d("OrderViewModel", "Order item: id=${item.id}, name='${item.itemName}', productId=${item.productId}")
//                                    }
//                                } else {
//                                    android.util.Log.w("OrderViewModel", "API returned empty items")
//                                    _existingOrderItems.value = emptyList()
//                                }
//                            }
//                            is Resource.Error<Order> -> {
//                                android.util.Log.w("OrderViewModel", "API failed to load order: ${resource.message}")
//                                // Fallback to local database
//                                fallbackToLocalDatabase(tableId)
//                            }
//                            is Resource.Loading<Order> -> {
//                                android.util.Log.d("OrderViewModel", "Loading order from API...")
//                            }
//                        }
//                    }
//                } else {
//                    android.util.Log.d("OrderViewModel", "No current order found in API, trying local database")
//                    // Fallback to local database
//                    fallbackToLocalDatabase(tableId)
//                }
//            } catch (e: Exception) {
//                android.util.Log.e("OrderViewModel", "Error loading existing orders: ${e.message}")
//                _existingOrderItems.value = emptyList()
//            } finally {
//                _isLoadingOrders.value = false
//            }
//        }
//    }
//
//    private suspend fun fallbackToLocalDatabase(tableId: Int) {
//        android.util.Log.d("OrderViewModel", "Falling back to local database for table $tableId")
//
//        // Get current order from local database
//        val currentOrder = orderRepository.getCurrentOrderByTable(tableId)
//        _currentOrder.value = currentOrder
//
//        if (currentOrder != null) {
//            android.util.Log.d("OrderViewModel", "Found current order ${currentOrder.id} in local database")
//
//            // Get order items from local database
//            val localItems = orderRepository.getOrderItems(currentOrder.id ?: 0)
//            val populatedLocalItems = localItems.map { orderItem ->
//                if (orderItem.itemName.isNullOrBlank() && orderItem.productId != null) {
//                    val menuItem = _allMenuItems.value.find { it.id == orderItem.productId }
//                    if (menuItem != null) {
//                        orderItem.copy(itemName = menuItem.name)
//                    } else {
//                        orderItem
//                    }
//                } else {
//                    orderItem
//                }
//            }
//            _existingOrderItems.value = populatedLocalItems
//            android.util.Log.d("OrderViewModel", "Loaded ${populatedLocalItems.size} items from local database")
//        } else {
//            android.util.Log.d("OrderViewModel", "No current order found in local database")
//            _existingOrderItems.value = emptyList()
//        }
//    }
//
//    private fun loadAllMenuItems() {
//        _isLoadingMenu.value = true
//        android.util.Log.d("OrderViewModel", "Starting to load menu items...")
//        viewModelScope.launch {
//            try {
//                when (val result = menuRepository.getAllMenuItems()) {
//                    is NetworkResult.Success<PaginatedResponse<MenuItem>> -> {
//                        val menuItems = result.data.items
//                        _allMenuItems.value = menuItems
//                        _filteredMenuItems.value = menuItems
//                        android.util.Log.d("OrderViewModel", "Successfully loaded ${menuItems.size} menu items")
//                    }
//                    is NetworkResult.Error<PaginatedResponse<MenuItem>> -> {
//                        android.util.Log.e("OrderViewModel", "Error loading menu: ${result.message}")
//                        _allMenuItems.value = emptyList()
//                        _filteredMenuItems.value = emptyList()
//                    }
//                    is NetworkResult.Loading<PaginatedResponse<MenuItem>> -> {
//                        android.util.Log.d("OrderViewModel", "Loading menu...")
//                    }
//                }
//            } catch (e: Exception) {
//                android.util.Log.e("OrderViewModel", "Exception loading menu: ${e.message}")
//                _allMenuItems.value = emptyList()
//                _filteredMenuItems.value = emptyList()
//            } finally {
//                _isLoadingMenu.value = false
//                android.util.Log.d("OrderViewModel", "Menu loading completed")
//            }
//        }
//    }
//
//    fun searchMenuItems(query: String) {
//        _searchQuery.value = query
//        val allItems = _allMenuItems.value
//        android.util.Log.d("OrderViewModel", "Searching for '$query' in ${allItems.size} items")
//
//        val filteredItems = if (query.isBlank()) {
//            allItems
//        } else {
//            allItems.filter { menuItem ->
//                menuItem.name.contains(query, ignoreCase = true) ||
//                        menuItem.description?.contains(query, ignoreCase = true) == true ||
//                        menuItem.categoryName?.contains(query, ignoreCase = true) == true
//            }
//        }
//
//        android.util.Log.d("OrderViewModel", "Found ${filteredItems.size} items for query '$query'")
//        _filteredMenuItems.value = filteredItems
//        android.util.Log.d("OrderViewModel", "Updated filteredMenuItems with ${filteredItems.size} items")
//    }
//
//    fun addMenuItemToOrder(menuItem: MenuItem, quantity: Int) {
//        if (currentTableId <= 0) {
//            _orderState.value = OrderState.Error("Không có thông tin bàn")
//            return
//        }
//
//        _orderState.value = OrderState.Loading
//
//        viewModelScope.launch {
//            try {
//                // If no current order exists, create one first
//                var order = _currentOrder.value
//                if (order == null) {
//                    val newOrder = Order(
//                        id = null,
//                        orderNumber = null,
//                        tableId = currentTableId,
//                        tableInfo = null,
//                        staffId = 1, // TODO: Get from current user
//                        staffName = null,
//                        customerCount = 0,
//                        status = "draft",
//                        subtotal = 0.0,
//                        discountAmount = 0.0,
//                        taxAmount = 0.0,
//                        serviceCharge = 0.0,
//                        totalAmount = 0.0,
//                        specialRequests = null,
//                        createdAt = null,
//                        submittedAt = null,
//                        completedAt = null
//                    )
//
//                    when (val result = orderRepository.createOrder(newOrder, emptyList())) {
//                        is NetworkResult.Success<Order> -> {
//                            order = result.data
//                            _currentOrder.value = order
//                        }
//                        is NetworkResult.Error<Order> -> {
//                            _orderState.value = OrderState.Error(result.message ?: "Lỗi tạo đơn hàng")
//                            return@launch
//                        }
//                        is NetworkResult.Loading<Order> -> {
//                            _orderState.value = OrderState.Loading
//                            return@launch
//                        }
//                    }
//                }
//
//                // Add item to the order
//                if (order != null) {
//                    val addItemRequest = AddOrderItemRequest(
//                        productId = menuItem.id,
//                        quantity = quantity,
//                        specialInstructions = null
//                    )
//
//                    // Check if item already exists in order
//                    val currentItems = _existingOrderItems.value.toMutableList()
//                    val existingItemIndex = currentItems.indexOfFirst { it.productId == menuItem.id }
//
//                    if (existingItemIndex != -1) {
//                        // Update existing item quantity
//                        val existingItem = currentItems[existingItemIndex]
//                        val newQuantity = (existingItem.quantity ?: 0) + quantity
//                        val updatedItem = existingItem.copy(
//                            quantity = newQuantity,
//                            totalPrice = (existingItem.unitPrice ?: 0.0) * newQuantity
//                        )
//                        currentItems[existingItemIndex] = updatedItem
//                        android.util.Log.d("OrderViewModel", "Updated existing item ${menuItem.name} quantity to $newQuantity")
//                    } else {
//                        // Create a new order item
//                        val tempOrderItem = OrderItem(
//                            id = null,
//                            orderId = order.id,
//                            productId = menuItem.id,
//                            itemName = menuItem.name,
//                            menuPrice = menuItem.price,
//                            imageUrl = menuItem.imageUrl,
//                            quantity = quantity,
//                            unitPrice = menuItem.price,
//                            totalPrice = menuItem.price * quantity,
//                            specialInstructions = null,
//                            status = "pending",
//                            createdAt = null,
//                            categoryName = menuItem.categoryName
//                        )
//                        currentItems.add(tempOrderItem)
//                        android.util.Log.d("OrderViewModel", "Added new item ${menuItem.name} with quantity $quantity")
//                        android.util.Log.d("OrderViewModel", "Created OrderItem: id=${tempOrderItem.id}, name='${tempOrderItem.itemName}', productId=${tempOrderItem.productId}")
//                    }
//
//                    _existingOrderItems.value = currentItems
//
//                    android.util.Log.d("OrderViewModel", "Added ${quantity}x ${menuItem.name} to order ${order.id ?: "N/A"}")
//
//                    // Update table status to occupied when first item is added
//                    if (currentItems.size == 1) {
//                        orderRepository.updateTableStatus(currentTableId, "occupied")
//                    }
//
//                    // Call API to add order item in background
//                    viewModelScope.launch {
//                        orderRepository.addOrderItem(order.id ?: 0, addItemRequest).collect { resource ->
//                            when (resource) {
//                                is Resource.Success -> {
//                                    android.util.Log.d("OrderViewModel", "Successfully added item to order via API: ${resource.data}")
//
//                                    // Update the order item with the real ID from API
//                                    val apiOrderItem = resource.data
//                                    if (apiOrderItem.id != null) {
//                                        val updatedItems = _existingOrderItems.value.toMutableList()
//                                        val index = updatedItems.indexOfFirst { it.productId == menuItem.id }
//                                        if (index != -1) {
//                                            updatedItems[index] = updatedItems[index].copy(id = apiOrderItem.id)
//                                            _existingOrderItems.value = updatedItems
//                                            android.util.Log.d("OrderViewModel", "Updated order item with real ID: ${apiOrderItem.id}")
//                                        }
//                                    }
//
//                                    // Update table order info with new total
//                                    val newTotal = calculateOrderTotal()
//                                    orderRepository.updateTableOrderInfo(currentTableId, order.id ?: 0, newTotal)
//                                }
//                                is Resource.Error -> {
//                                    android.util.Log.w("OrderViewModel", "Failed to add item via API: ${resource.message}")
//                                    // Still continue with local state update
//                                }
//                                is Resource.Loading -> {
//                                    // Continue with local state update
//                                }
//                            }
//                        }
//                    }
//
//                    _orderState.value = OrderState.Success(order)
//                }
//            } catch (e: Exception) {
//                _orderState.value = OrderState.Error("Lỗi: ${e.message}")
//            }
//        }
//    }
//
//    fun updateOrderItemQuantity(orderItem: OrderItem, newQuantity: Int) {
//        if (newQuantity <= 0) {
//            removeOrderItem(orderItem)
//        } else {
//            // Update the order item quantity in the list
//            val currentItems = _existingOrderItems.value.toMutableList()
//            val index = currentItems.indexOfFirst { it.id == orderItem.id }
//            if (index != -1) {
//                val updatedItem = orderItem.copy(
//                    quantity = newQuantity,
//                    totalPrice = (orderItem.unitPrice ?: 0.0) * newQuantity
//                )
//                currentItems[index] = updatedItem
//                _existingOrderItems.value = currentItems
//                android.util.Log.d("OrderViewModel", "Updated ${orderItem.itemName} quantity to $newQuantity")
//            }
//
//            // Call API to update order item in background
//            viewModelScope.launch {
//                val currentOrder = _currentOrder.value
//                if (currentOrder != null) {
//                    android.util.Log.d("OrderViewModel", "Updating order item: orderId=${currentOrder.id}, orderItemId=${orderItem.id}, newQuantity=$newQuantity")
//
//                    // Check if orderItem.id is null or 0
//                    if (orderItem.id == null || orderItem.id == 0) {
//                        android.util.Log.w("OrderViewModel", "OrderItem ID is null or 0, cannot update via API")
//                        return@launch
//                    }
//
//                    orderRepository.updateOrderItem(currentOrder.id ?: 0, orderItem.id ?: 0, UpdateOrderItemRequest(
//                        quantity = newQuantity,
//                        specialInstructions = orderItem.specialInstructions
//                    )).collect { resource ->
//                        when (resource) {
//                            is Resource.Success -> {
//                                android.util.Log.d("OrderViewModel", "Successfully updated item via API")
//
//                                // Update table order info with new total
//                                val newTotal = calculateOrderTotal()
//                                android.util.Log.d("OrderViewModel", "Updating table order info: tableId=$currentTableId, orderId=${currentOrder.id}, newTotal=$newTotal")
//                                orderRepository.updateTableOrderInfo(currentTableId, currentOrder.id ?: 0, newTotal)
//
//                                // Refresh table data to update UI
//                                refreshTableData(currentTableId)
//                            }
//                            is Resource.Error -> {
//                                android.util.Log.w("OrderViewModel", "Failed to update item via API: ${resource.message}")
//                            }
//                            is Resource.Loading -> {
//                                // Continue with local state update
//                            }
//                        }
//                    }
//                }
//            }
//        }
//    }
//
//    fun removeOrderItem(orderItem: OrderItem) {
//        // Remove the order item from the list
//        val currentItems = _existingOrderItems.value.toMutableList()
//        currentItems.removeAll { it.id == orderItem.id }
//        _existingOrderItems.value = currentItems
//        android.util.Log.d("OrderViewModel", "Removed ${orderItem.itemName} from order")
//
//        // Call API to delete order item in background
//        viewModelScope.launch {
//            val currentOrder = _currentOrder.value
//            if (currentOrder != null) {
//                android.util.Log.d("OrderViewModel", "Deleting order item: orderId=${currentOrder.id}, orderItemId=${orderItem.id}")
//
//                // Check if orderItem.id is null or 0
//                if (orderItem.id == null || orderItem.id == 0) {
//                    android.util.Log.w("OrderViewModel", "OrderItem ID is null or 0, cannot delete via API")
//                    return@launch
//                }
//
//                orderRepository.deleteOrderItem(currentOrder.id ?: 0, orderItem.id ?: 0).collect { resource ->
//                    when (resource) {
//                        is Resource.Success -> {
//                            android.util.Log.d("OrderViewModel", "Successfully deleted item via API")
//
//                            // Update table order info with new total
//                            val newTotal = calculateOrderTotal()
//                            android.util.Log.d("OrderViewModel", "Updating table order info: tableId=$currentTableId, orderId=${currentOrder.id}, newTotal=$newTotal")
//                            orderRepository.updateTableOrderInfo(currentTableId, currentOrder.id ?: 0, newTotal)
//
//                            // Refresh table data to update UI
//                            refreshTableData(currentTableId)
//                        }
//                        is Resource.Error -> {
//                            android.util.Log.w("OrderViewModel", "Failed to delete item via API: ${resource.message}")
//                        }
//                        is Resource.Loading -> {
//                            // Continue with local state update
//                        }
//                    }
//                }
//            }
//        }
//    }
//
//    fun submitOrder() {
//        val order = _currentOrder.value ?: return
//        val orderItems = _existingOrderItems.value
//
//        if (orderItems.isEmpty()) {
//            _orderState.value = OrderState.Error("Không có món nào để gửi")
//            return
//        }
//
//        // Check if order is already submitted
//        if (order.status == "submitted" || order.status == "confirmed" || order.status == "preparing" || order.status == "ready" || order.status == "served") {
//            _orderState.value = OrderState.Error("Đơn hàng đã được gửi trước đó")
//            return
//        }
//
//        android.util.Log.d("OrderViewModel", "Starting submit order process...")
//        _orderState.value = OrderState.Loading
//        viewModelScope.launch {
//            try {
//                android.util.Log.d("OrderViewModel", "Submitting order ${order.id ?: "N/A"} with ${orderItems.size} items")
//
//                // First, save all order items to database (while order is still in draft status)
//                android.util.Log.d("OrderViewModel", "Saving ${orderItems.size} order items to database...")
//                var allItemsSaved = true
//
//                // Save items sequentially to ensure they are all saved before submitting
//                for (orderItem in orderItems) {
//                    android.util.Log.d("OrderViewModel", "Saving item: ${orderItem.itemName}, quantity: ${orderItem.quantity}")
//
//                    // Use a suspend function to wait for completion
//                    val saveResult = orderRepository.addOrderItem(order.id ?: 0, AddOrderItemRequest(
//                        productId = orderItem.productId,
//                        quantity = orderItem.quantity ?: 0,
//                        specialInstructions = orderItem.specialInstructions
//                    )).first() // Get the first (and only) result
//
//                    when (saveResult) {
//                        is Resource.Success -> {
//                            android.util.Log.d("OrderViewModel", "Successfully saved item: ${orderItem.itemName}")
//                        }
//                        is Resource.Error -> {
//                            android.util.Log.e("OrderViewModel", "Error saving item ${orderItem.itemName}: ${saveResult.message}")
//                            allItemsSaved = false
//                        }
//                        is Resource.Loading -> {
//                            android.util.Log.d("OrderViewModel", "Saving item: ${orderItem.itemName}...")
//                        }
//                    }
//                }
//
//                if (!allItemsSaved) {
//                    _orderState.value = OrderState.Error("Lỗi khi lưu một số món ăn")
//                    return@launch
//                }
//
//                // Then submit the order
//                android.util.Log.d("OrderViewModel", "Calling submitOrder API...")
//                orderRepository.submitOrder(order.id ?: 0).collect { resource ->
//                    when (resource) {
//                        is Resource.Success -> {
//                            android.util.Log.d("OrderViewModel", "Submit order API success!")
//                            // Update order status to submitted
//                            val updatedOrder = order.copy(status = "submitted")
//                            _currentOrder.value = updatedOrder
//
//                            // Update table's current order info
//                            android.util.Log.d("OrderViewModel", "Updating table order info...")
//                            android.util.Log.d("OrderViewModel", "Table ID: $currentTableId, Order ID: ${order.id}, Total: ${calculateOrderTotal()}")
//                            orderRepository.updateTableOrderInfo(currentTableId, order.id ?: 0, calculateOrderTotal())
//
//                            _orderState.value = OrderState.Success(updatedOrder)
//                            android.util.Log.d("OrderViewModel", "Order submitted successfully")
//                        }
//                        is Resource.Error -> {
//                            android.util.Log.e("OrderViewModel", "Submit order API error: ${resource.message}")
//                            _orderState.value = OrderState.Error(resource.message ?: "Lỗi gửi đơn hàng")
//                        }
//                        is Resource.Loading -> {
//                            android.util.Log.d("OrderViewModel", "Submit order API still loading...")
//                            _orderState.value = OrderState.Loading
//                        }
//                    }
//                }
//            } catch (e: Exception) {
//                android.util.Log.e("OrderViewModel", "Exception in submitOrder: ${e.message}", e)
//                _orderState.value = OrderState.Error("Lỗi: ${e.message}")
//            }
//        }
//    }
//
//    fun calculateOrderTotal(): Double {
//        val total = _existingOrderItems.value.sumOf { (it.quantity ?: 0) * (it.unitPrice ?: 0.0) }
//        android.util.Log.d("OrderViewModel", "Calculated total: $total for ${_existingOrderItems.value.size} items")
//        return total
//    }
//}
//
//// Data classes
//data class CartItem(
//    val menuItem: MenuItem,
//    val quantity: Int
//)
//
//sealed class OrderState {
//    object Idle : OrderState()
//    object Loading : OrderState()
//    data class Success(val order: Order?) : OrderState()
//    data class Error(val message: String) : OrderState()
//}
