package com.restaurant.staff.data.local

import androidx.room.*
import com.restaurant.staff.data.model.Order
import com.restaurant.staff.data.model.OrderItem
import kotlinx.coroutines.flow.Flow

@Dao
interface OrderDao {

    // Orders
    @Query("SELECT * FROM orders WHERE user_id = :userId ORDER BY created_at DESC")
    fun getOrdersByStaffFlow(userId: Int): Flow<List<Order>>

    @Query("SELECT * FROM orders WHERE user_id = :userId ORDER BY created_at DESC")
    suspend fun getOrdersByStaff(userId: Int): List<Order>

    @Query("SELECT * FROM orders WHERE id = :orderId")
    suspend fun getOrderById(orderId: Int): Order?

    @Query("SELECT * FROM orders WHERE table_id = :tableId AND status NOT IN ('completed', 'cancelled') ORDER BY created_at DESC LIMIT 1")
    suspend fun getCurrentOrderByTable(tableId: Int): Order?

    @Query("SELECT * FROM orders WHERE status = :status ORDER BY created_at ASC")
    suspend fun getOrdersByStatus(status: String): List<Order>

    @Query("SELECT * FROM orders WHERE status NOT IN ('completed', 'cancelled') ORDER BY created_at ASC")
    suspend fun getActiveOrders(): List<Order>

    @Query("SELECT * FROM orders WHERE DATE(created_at) = DATE('now') AND user_id = :userId ORDER BY created_at DESC")
    suspend fun getTodayOrdersByStaff(userId: Int): List<Order>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertOrder(order: Order): Long

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertOrders(orders: List<Order>)

    @Update
    suspend fun updateOrder(order: Order)

    @Delete
    suspend fun deleteOrder(order: Order)

    @Query("DELETE FROM orders WHERE id = :orderId")
    suspend fun deleteOrderById(orderId: Int)

    @Query("DELETE FROM orders")
    suspend fun deleteAllOrders()

    @Query("UPDATE orders SET status = :status WHERE id = :orderId")
    suspend fun updateOrderStatus(orderId: Int, status: String)

    // Order Items
    @Query("SELECT * FROM order_items WHERE order_id = :orderId ORDER BY created_at ASC")
    fun getOrderItemsFlow(orderId: Int): Flow<List<OrderItem>>

    @Query("SELECT * FROM order_items WHERE order_id = :orderId ORDER BY created_at ASC")
    suspend fun getOrderItems(orderId: Int): List<OrderItem>

    @Query("SELECT * FROM order_items WHERE id = :itemId")
    suspend fun getOrderItemById(itemId: Int): OrderItem?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertOrderItem(orderItem: OrderItem): Long

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertOrderItems(orderItems: List<OrderItem>)

    @Update
    suspend fun updateOrderItem(orderItem: OrderItem)

    @Delete
    suspend fun deleteOrderItem(orderItem: OrderItem)

    @Query("DELETE FROM order_items WHERE order_id = :orderId")
    suspend fun deleteOrderItemsByOrderId(orderId: Int)

    @Query("DELETE FROM order_items")
    suspend fun deleteAllOrderItems()

    @Query("UPDATE order_items SET status = :status WHERE id = :itemId")
    suspend fun updateOrderItemStatus(itemId: Int, status: String)

    @Query("SELECT COUNT(*) FROM order_items WHERE order_id = :orderId")
    suspend fun getOrderItemCount(orderId: Int): Int

    @Query("SELECT SUM(total_price) FROM order_items WHERE order_id = :orderId")
    suspend fun getOrderSubtotal(orderId: Int): Double?
}

