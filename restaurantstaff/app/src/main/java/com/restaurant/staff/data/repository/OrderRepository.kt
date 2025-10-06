package com.restaurant.staff.data.repository

import com.restaurant.staff.data.local.OrderDao
import com.restaurant.staff.data.local.TableDao
import com.restaurant.staff.data.model.*
import com.restaurant.staff.data.remote.OrderApiService
import com.restaurant.staff.utils.NetworkUtils
import com.restaurant.staff.data.model.NetworkResult
import com.restaurant.staff.data.remote.TableApiService
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow

class OrderRepository(
    private val orderApiService: OrderApiService,
    private val orderDao: OrderDao,
    private val tableDao: TableDao,
    private val tableApiService: TableApiService
) {
    suspend fun syncCurrentOrderByTable(tableId: Int): CurrentOrderPayload? {
        return try {
            when (val result = NetworkUtils.safeApiCall {
                tableApiService.getCurrentOrder(tableId)
            }) {
                is NetworkResult.Success -> {
                    val payload = result.data as? CurrentOrderPayload
                    var order = payload?.order
                    if (order != null) {
                        // Cache order
                        orderDao.insertOrder(order)
                        // Cache items if present
                        order.items?.let { items ->
                            if (items.isNotEmpty()) {
                                orderDao.insertOrderItems(items)
                            }
                        }
                        // Trả payload với order đầy đủ
                        payload
                    } else {
                        // Thử fallback sang /tables/{id}/details để lấy items/pending
                        when (val td = NetworkUtils.safeApiCall { tableApiService.getTableDetails(tableId) }) {
                            is NetworkResult.Success -> {
                                val details = td.data
                                // Nếu API trả order và danh sách món, gắn vào mô hình
                                val apiOrder = details.order
                                if (apiOrder != null) {
                                    // tạo bản sao với items mới (items là val trong data class)
                                    val mergedOrder = apiOrder.copy(
                                        items = details.orderItems ?: emptyList()
                                    )
                                    mergedOrder.pendingItems = details.pendingItems ?: emptyList()
                                    // cache order và confirmed items
                                    orderDao.insertOrder(mergedOrder)
                                    if (!mergedOrder.items.isNullOrEmpty()) {
                                        orderDao.insertOrderItems(mergedOrder.items!!)
                                    }
                                    // Trả payload với order mới merge
                                    CurrentOrderPayload(order = mergedOrder)
                                } else {
                                    null
                                }
                            }
                            else -> {
                                // Không có order hoạt động: dọn cache cũ
                                try {
                                    val localOrder = orderDao.getCurrentOrderByTable(tableId)
                                    localOrder?.id?.let { oid ->
                                        orderDao.deleteOrderItemsByOrderId(oid)
                                        orderDao.deleteOrderById(oid)
                                    }
                                } catch (e: Exception) {
                                    android.util.Log.w("OrderRepository", "clear local cache failed: ${e.message}")
                                }
                                null
                            }
                        }
                    }
                }
                is NetworkResult.Error -> {
                    // Fallback to local cache
                    val localOrder = orderDao.getCurrentOrderByTable(tableId)
                    if (localOrder != null) {
                        CurrentOrderPayload(order = localOrder)
                    } else {
                        null
                    }
                }
                is NetworkResult.Loading -> {
                    // Return local cache while loading
                    val localOrder = orderDao.getCurrentOrderByTable(tableId)
                    if (localOrder != null) {
                        CurrentOrderPayload(order = localOrder)
                    } else {
                        null
                    }
                }
            }
        } catch (e: Exception) {
            // Fallback to local cache on any exception
            val localOrder = orderDao.getCurrentOrderByTable(tableId)
            if (localOrder != null) {
                CurrentOrderPayload(order = localOrder)
            } else {
                null
            }
        }
    }
    suspend fun getOrders(
        page: Int = 1,
        limit: Int = 20,
        status: String? = null,
        tableId: Int? = null
    ): Flow<Resource<List<Order>>> = flow {
        emit(Resource.Loading())

        try {
            when (val result = NetworkUtils.safeApiCall {
                orderApiService.getOrders(page = page, limit = limit, status = status, tableId = tableId)
            }) {
                is NetworkResult.Success -> {
                    val response = result.data
                    val orders = response.orders
                    // Cache
                    orderDao.insertOrders(orders)
                    emit(Resource.Success(orders))
                }
                is NetworkResult.Error -> {
                    // Fallback to local cache (basic strategy)
                    val cachedOrders = if (status != null) {
                        orderDao.getOrdersByStatus(status)
                    } else {
                        // If no filter, return active orders as sensible default
                        orderDao.getActiveOrders()
                    }
                    emit(Resource.Success(cachedOrders))
                }
                is NetworkResult.Loading -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            val cachedOrders = if (status != null) {
                orderDao.getOrdersByStatus(status)
            } else {
                orderDao.getActiveOrders()
            }
            emit(Resource.Success(cachedOrders))
        }
    }

    fun getOrdersByStaffFlow(staffId: Int): Flow<List<Order>> {
        return orderDao.getOrdersByStaffFlow(staffId)
    }

    fun getOrderItemsFlow(orderId: Int): Flow<List<OrderItem>> {
        return orderDao.getOrderItemsFlow(orderId)
    }

    suspend fun createOrder(request: CreateOrderRequest): Flow<Resource<Order>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall { orderApiService.createOrder(request) }

            when (result) {
                is NetworkResult.Success<Order> -> {
                    val order = result.data
                    orderDao.insertOrder(order)
                    emit(Resource.Success(order))
                }
                is NetworkResult.Error<Order> -> {
                    emit(Resource.Error(result.message))
                }
                is NetworkResult.Loading<Order> -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            emit(Resource.Error(e.message ?: "Failed to create order"))
        }
    }


    suspend fun getOrderDetails(orderId: Int?): Flow<Resource<Order>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall { orderApiService.getOrderDetails(orderId ?: 0) }

            when (result) {
                is NetworkResult.Success<Order> -> {
                    val order = result.data
                    orderDao.insertOrder(order)

                    // Cache order items if available
                    order.items?.let { items ->
                        orderDao.insertOrderItems(items)
                    }

                    emit(Resource.Success(order))
                }
                is NetworkResult.Error<Order> -> {
                    // Try to get cached order
                    val cachedOrder = orderDao.getOrderById(orderId ?: 0)
                    if (cachedOrder != null) {
                        emit(Resource.Success(cachedOrder))
                    } else {
                        emit(Resource.Error(result.message))
                    }
                }
                is NetworkResult.Loading<Order> -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            // Try to get cached order
            val cachedOrder = orderDao.getOrderById(orderId ?: 0)
            if (cachedOrder != null) {
                emit(Resource.Success(cachedOrder))
            } else {
                emit(Resource.Error(e.message ?: "Failed to get order details"))
            }
        }
    }

    suspend fun updateOrder(orderId: Int?, request: UpdateOrderRequest): Flow<Resource<Order>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall {
                orderApiService.updateOrder(orderId ?: 0, request)
            }

            when (result) {
                is NetworkResult.Success<Order> -> {
                    val order = result.data
                    orderDao.insertOrder(order)
                    emit(Resource.Success(order))
                }
                is NetworkResult.Error<Order> -> {
                    emit(Resource.Error(result.message))
                }
                is NetworkResult.Loading<Order> -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            emit(Resource.Error(e.message ?: "Failed to update order"))
        }
    }

    suspend fun submitOrder(orderId: Int?): Flow<Resource<Unit>> = flow {
        android.util.Log.d("OrderRepository", "submitOrder called with orderId: $orderId")
        emit(Resource.Loading())

        try {
            android.util.Log.d("OrderRepository", "Calling API submitOrder...")
            val result = NetworkUtils.safeApiCallUnit { orderApiService.submitOrder(orderId?:0) }
            android.util.Log.d("OrderRepository", "API call completed, result: $result")

            when (result) {
                is NetworkResult.Success<Unit> -> {
                    android.util.Log.d("OrderRepository", "Submit order success, updating local status...")
                    // Update local order status
                    orderDao.updateOrderStatus(orderId ?: 0, "confirmed")
                    emit(Resource.Success(Unit))
                }
                is NetworkResult.Error<Unit> -> {
                    android.util.Log.e("OrderRepository", "Submit order error: ${result.message}")
                    emit(Resource.Error(result.message))
                }
                is NetworkResult.Loading<Unit> -> {
                    android.util.Log.d("OrderRepository", "Submit order still loading...")
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            android.util.Log.e("OrderRepository", "Exception in submitOrder: ${e.message}", e)
            emit(Resource.Error(e.message ?: "Failed to submit order"))
        }
    }

    suspend fun addOrderItem(orderId: Int, request: AddOrderItemRequest): Flow<Resource<OrderItem>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall {
                orderApiService.addOrderItem(orderId, request)
            }

            when (result) {
                is NetworkResult.Success<OrderItem> -> {
                    val orderItem = result.data
                    // Only insert if it has a real ID (not a mock pending item with id=null)
                    if (orderItem.id != null && orderItem.id > 0) {
                        orderDao.insertOrderItem(orderItem)
                    } else {
                        android.util.Log.d("OrderRepository", "Skipping insert of pending item (id=${orderItem.id})")
                    }
                    emit(Resource.Success(orderItem))
                }
                is NetworkResult.Error<OrderItem> -> {
                    emit(Resource.Error(result.message))
                }
                is NetworkResult.Loading<OrderItem> -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            emit(Resource.Error(e.message ?: "Failed to add item to order"))
        }
    }

    suspend fun updateOrderItem(orderId: Int, orderItemId: Int, request: UpdateOrderItemRequest): Flow<Resource<OrderItem>> = flow {
        emit(Resource.Loading())

        try {
            android.util.Log.d("OrderRepository", "updateOrderItem called: orderId=$orderId, orderItemId=$orderItemId, request=$request")

            val result = NetworkUtils.safeApiCall {
                orderApiService.updateOrderItem(orderId, orderItemId, request)
            }

            when (result) {
                is NetworkResult.Success<OrderItem> -> {
                    val orderItem = result.data
                    orderDao.insertOrderItem(orderItem)
                    emit(Resource.Success(orderItem))
                }
                is NetworkResult.Error<OrderItem> -> {
                    emit(Resource.Error(result.message))
                }
                is NetworkResult.Loading<OrderItem> -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            emit(Resource.Error(e.message ?: "Failed to update order item"))
        }
    }

    suspend fun deleteOrderItem(orderId: Int, orderItemId: Int): Flow<Resource<Unit>> = flow {
        emit(Resource.Loading())

        try {
            android.util.Log.d("OrderRepository", "deleteOrderItem called: orderId=$orderId, orderItemId=$orderItemId")

            val result = NetworkUtils.safeApiCallUnit {
                orderApiService.deleteOrderItem(orderId, orderItemId)
            }

            when (result) {
                is NetworkResult.Success<Unit> -> {
                    // Remove from local cache
                    val orderItem = orderDao.getOrderItemById(orderItemId)
                    orderItem?.let { orderDao.deleteOrderItem(it) }
                    emit(Resource.Success(Unit))
                }
                is NetworkResult.Error<Unit> -> {
                    emit(Resource.Error(result.message))
                }
                is NetworkResult.Loading<Unit> -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            emit(Resource.Error(e.message ?: "Failed to delete order item"))
        }
    }

    suspend fun processPayment(orderId: Int, request: PaymentRequest): Flow<Resource<PaymentResponse>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall {
                orderApiService.processPayment(orderId, request)
            }

            when (result) {
                is NetworkResult.Success<PaymentResponse> -> {
                    val paymentResponse = result.data

                    // Update local order status to completed
                    orderDao.updateOrderStatus(orderId, "completed")

                    emit(Resource.Success(paymentResponse))
                }
                is NetworkResult.Error<PaymentResponse> -> {
                    emit(Resource.Error(result.message))
                }
                is NetworkResult.Loading<PaymentResponse> -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            emit(Resource.Error(e.message ?: "Failed to process payment"))
        }
    }

    suspend fun getMyOrders(date: String?, staffId: Int): Flow<Resource<List<Order>>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall {
                orderApiService.getMyOrders(date, 1, 50)
            }

            when (result) {
                is NetworkResult.Success<Map<String, Any>> -> {
                    // Extract orders from response (API returns Map<String, Any>)
                    val responseData = result.data
                    val orders = responseData["orders"] as? List<Order> ?: emptyList()

                    // Cache orders
                    orderDao.insertOrders(orders)

                    emit(Resource.Success(orders))
                }
                is NetworkResult.Error<Map<String, Any>> -> {
                    // Return cached orders
                    val cachedOrders = if (date != null) {
                        orderDao.getTodayOrdersByStaff(staffId)
                    } else {
                        orderDao.getOrdersByStaff(staffId)
                    }
                    emit(Resource.Success(cachedOrders))
                }
                is NetworkResult.Loading<Map<String, Any>> -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            // Return cached orders
            val cachedOrders = if (date != null) {
                orderDao.getTodayOrdersByStaff(staffId)
            } else {
                orderDao.getOrdersByStaff(staffId)
            }
            emit(Resource.Success(cachedOrders))
        }
    }

    // Local operations - Keep these as suspend functions for direct access
    suspend fun getOrderById(orderId: Int): Order? {
        return orderDao.getOrderById(orderId)
    }

    suspend fun getCurrentOrderByTable(tableId: Int): Order? {
        return orderDao.getCurrentOrderByTable(tableId)
    }

    suspend fun getActiveOrders(): List<Order> {
        return orderDao.getActiveOrders()
    }

    suspend fun getOrderItems(orderId: Int): List<OrderItem> {
        return orderDao.getOrderItems(orderId)
    }

    suspend fun getOrderItemCount(orderId: Int): Int {
        return orderDao.getOrderItemCount(orderId)
    }

    suspend fun getOrderSubtotal(orderId: Int): Double {
        return orderDao.getOrderSubtotal(orderId) ?: 0.0
    }

    suspend fun updateTableOrderInfo(tableId: Int, orderId: Int, totalAmount: Double) {
        try {
            android.util.Log.d("OrderRepository", "updateTableOrderInfo called: tableId=$tableId, orderId=$orderId, totalAmount=$totalAmount")

            // Update table's current order info in local database
            tableDao.updateTableOrderInfo(tableId)

            // Also update via API
            val request = UpdateTableOrderInfoRequest(orderId, totalAmount)
            android.util.Log.d("OrderRepository", "Calling API to update table order info: $request")

            when (val result = NetworkUtils.safeApiCallMessageOnly {
                tableApiService.updateTableOrderInfo(tableId, request)
            }) {
                is NetworkResult.Success<Unit> -> {
                    android.util.Log.d("OrderRepository", "Successfully updated table order info via API")
                }
                is NetworkResult.Error<Unit> -> {
                    android.util.Log.w("OrderRepository", "Failed to update table order info via API: ${result.message}")
                }
                is NetworkResult.Loading<Unit> -> {
                    android.util.Log.d("OrderRepository", "Updating table order info via API...")
                }
            }
        } catch (e: Exception) {
            android.util.Log.e("OrderRepository", "Error updating table order info: ${e.message}")
        }
    }

    suspend fun updateTableStatus(tableId: Int, status: String) {
        try {
            // Update table status in local database
            tableDao.updateTableStatus(tableId, status)

            // Also update via API if needed
            // tableApiService.updateTableStatus(tableId, status)
        } catch (e: Exception) {
            android.util.Log.e("OrderRepository", "Error updating table status: ${e.message}")
        }
    }

    suspend fun getTableInfo(tableId: Int): Flow<Resource<Table?>> = flow {
        emit(Resource.Loading())

        try {
            // Get current order for this table from API
            when (val result = NetworkUtils.safeApiCall {
                tableApiService.getCurrentOrder(tableId)
            }) {
                is NetworkResult.Success -> {
                    val payload = result.data as? CurrentOrderPayload
                    val order = payload?.order
                    if (order != null) {
                        // Create a table object with current order info
                        val table = Table(
                            id = tableId,
                            name = "Table $tableId", // Use name field instead of tableNumber/tableName
                            areaId = 0, // We don't need this for order loading
                            capacity = 0,
                            status = order.status ?: "occupied",
                            positionX = 0,
                            positionY = 0,
                            createdAt = order.createdAt,
                            updatedAt = order.createdAt,
                            // Additional fields for order management
                            tableNumber = "Table $tableId",
                            tableName = "Table $tableId",
                            qrCode = null,
                            currentOrderId = order.id,
                            orderNumber = order.orderNumber,
                            orderStatus = order.status,
                            customerCount = order.customerCount,
                            totalAmount = order.totalAmount,
                            orderCreatedAt = order.createdAt,
                            waiterName = order.staffName,
                            areaName = null,
                            pendingAmount = order.totalAmount ?: 0.0,
                            activeOrders = 1
                        )
                        emit(Resource.Success(table))
                    } else {
                        emit(Resource.Success(null))
                    }
                }
                is NetworkResult.Error -> {
                    android.util.Log.w("OrderRepository", "Failed to get current order from API: ${result.message}")
                    // Fallback to local database
                    val localTable = tableDao.getTableById(tableId)
                    emit(Resource.Success(localTable))
                }
                is NetworkResult.Loading -> {
                    android.util.Log.d("OrderRepository", "Loading current order from API...")
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            android.util.Log.e("OrderRepository", "Error getting table info: ${e.message}")
            // Fallback to local database
            val localTable = tableDao.getTableById(tableId)
            emit(Resource.Success(localTable))
        }
    }
}

