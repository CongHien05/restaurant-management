package com.restaurant.staff.data.model

import android.os.Parcelable
import com.restaurant.staff.utils.toVietnamCurrency
import androidx.room.ColumnInfo
import androidx.room.Entity
import androidx.room.PrimaryKey
import com.google.gson.annotations.SerializedName
import androidx.room.Ignore
import kotlinx.parcelize.Parcelize

@Parcelize
@Entity(tableName = "orders")
data class Order(
    @PrimaryKey
    @SerializedName("id")
    val id: Int,

    @ColumnInfo(name = "table_id")
    @SerializedName("table_id")
    val tableId: Int,

    @ColumnInfo(name = "user_id")
    @SerializedName("user_id")
    val userId: Int,

    @ColumnInfo(name = "order_number")
    @SerializedName("order_number")
    val orderNumber: String,

    @SerializedName("status")
    val status: String, // pending, confirmed, preparing, ready, served, completed, cancelled

    @ColumnInfo(name = "total_amount")
    @SerializedName("total_amount")
    val totalAmount: Double,

    @ColumnInfo(name = "payment_status")
    @SerializedName("payment_status")
    val paymentStatus: String, // pending, paid, refunded

    @ColumnInfo(name = "payment_method")
    @SerializedName("payment_method")
    val paymentMethod: String?, // cash, card, transfer

    @SerializedName("notes")
    val notes: String?,

    @ColumnInfo(name = "created_at")
    @SerializedName("created_at")
    val createdAt: String,

    @ColumnInfo(name = "updated_at")
    @SerializedName("updated_at")
    val updatedAt: String,

    // Additional fields for order management (not in database)
    @SerializedName("customer_count")
    val customerCount: Int? = null,

    @SerializedName("special_requests")
    val specialRequests: String? = null,

    @SerializedName("staff_name")
    val staffName: String? = null,

    @SerializedName("items")
    val items: List<OrderItem>? = null
) : Parcelable {
    @Ignore
    @SerializedName("pending_items")
    var pendingItems: List<PendingItem>? = null

    val statusText: String
        get() = when (status) {
            "pending" -> "Chờ xử lý"
            "confirmed" -> "Đã xác nhận"
            "preparing" -> "Đang chế biến"
            "ready" -> "Sẵn sàng"
            "served" -> "Đã phục vụ"
            "completed" -> "Hoàn thành"
            "cancelled" -> "Đã hủy"
            else -> status
        }

    val statusColor: Int
        get() = when (status) {
            "pending" -> android.graphics.Color.parseColor("#FF9800")     // Orange
            "confirmed" -> android.graphics.Color.parseColor("#2196F3")   // Blue
            "preparing" -> android.graphics.Color.parseColor("#FF5722")   // Deep Orange
            "ready" -> android.graphics.Color.parseColor("#4CAF50")       // Green
            "served" -> android.graphics.Color.parseColor("#8BC34A")      // Light Green
            "completed" -> android.graphics.Color.parseColor("#795548")   // Brown
            "cancelled" -> android.graphics.Color.parseColor("#F44336")   // Red
            else -> android.graphics.Color.parseColor("#9E9E9E")
        }

    val formattedTotal: String
        get() = totalAmount.toVietnamCurrency()

    val canEdit: Boolean
        get() = status == "pending"

    val canPay: Boolean
        get() = status in listOf("ready", "served") && paymentStatus == "pending"
}

@Parcelize
data class PendingItem(
    @SerializedName("id")
    val id: Int? = null,

    @SerializedName("product_id")
    val productId: Int,

    @SerializedName("item_name")
    val itemName: String,

    @SerializedName("quantity")
    val quantity: Int,

    @SerializedName("special_instructions")
    val specialInstructions: String? = null
) : Parcelable

@Parcelize
data class CurrentOrderPayload(
    @SerializedName("order")
    val order: Order?
) : Parcelable

@Parcelize
data class TableDetailsPayload(
    @SerializedName("table") val table: Table?,
    @SerializedName("order") val order: Order?,
    @SerializedName("order_items") val orderItems: List<OrderItem>?,
    @SerializedName("pending_items") val pendingItems: List<PendingItem>?
) : Parcelable

@Parcelize
@Entity(tableName = "order_items")
data class OrderItem(
    @PrimaryKey
    @SerializedName("id")
    val id: Int,

    @ColumnInfo(name = "order_id")
    @SerializedName("order_id")
    val orderId: Int,

    @ColumnInfo(name = "product_id")
    @SerializedName("product_id")
    val productId: Int,

    @SerializedName("quantity")
    val quantity: Int,

    @ColumnInfo(name = "unit_price")
    @SerializedName("unit_price")
    val unitPrice: Double,

    @ColumnInfo(name = "total_price")
    @SerializedName("total_price")
    val totalPrice: Double,

    @SerializedName("notes")
    val notes: String?,

    @SerializedName("item_name")
    val itemName: String?,

    @SerializedName("special_instructions")
    val specialInstructions: String?,

    @SerializedName("status")
    val status: String, // pending, preparing, ready, served

    @ColumnInfo(name = "created_at")
    @SerializedName("created_at")
    val createdAt: String,

    @ColumnInfo(name = "updated_at")
    @SerializedName("updated_at")
    val updatedAt: String
) : Parcelable {

    val formattedPrice: String
        get() = totalPrice.toVietnamCurrency()

    val formattedUnitPrice: String
        get() = unitPrice.toVietnamCurrency()

    val statusText: String
        get() = when (status) {
            "pending" -> "Chờ xử lý"
            "preparing" -> "Đang chế biến"
            "ready" -> "Sẵn sàng"
            "served" -> "Đã phục vụ"
            else -> status
        }
}

// Request models
@Parcelize
data class CreateOrderRequest(
    @SerializedName("table_id")
    val tableId: Int,

    @SerializedName("customer_count")
    val customerCount: Int,

    @SerializedName("special_requests")
    val specialRequests: String?
) : Parcelable

@Parcelize
data class UpdateOrderRequest(
    @SerializedName("customer_count")
    val customerCount: Int?,

    @SerializedName("special_requests")
    val specialRequests: String?,

    @SerializedName("discount_amount")
    val discountAmount: Double?
) : Parcelable

@Parcelize
data class AddOrderItemRequest(
    @SerializedName("product_id")
    val productId: Int,

    @SerializedName("quantity")
    val quantity: Int,

    @SerializedName("special_instructions")
    val specialInstructions: String?
) : Parcelable

@Parcelize
data class UpdateOrderItemRequest(
    @SerializedName("quantity")
    val quantity: Int?,

    @SerializedName("special_instructions")
    val specialInstructions: String?
) : Parcelable

@Parcelize
data class PaymentRequest(
    @SerializedName("payment_method")
    val paymentMethod: String, // cash, card, bank_transfer, e_wallet, voucher

    @SerializedName("amount")
    val amount: Double,

    @SerializedName("received_amount")
    val receivedAmount: Double?,

    @SerializedName("notes")
    val notes: String?
) : Parcelable

@Parcelize
data class PaymentResponse(
    @SerializedName("payment")
    val payment: Payment,

    @SerializedName("order")
    val order: PaymentOrderInfo
) : Parcelable

@Parcelize
data class Payment(
    @SerializedName("id")
    val id: Int?,

    @SerializedName("payment_method")
    val paymentMethod: String,

    @SerializedName("amount")
    val amount: Double,

    @SerializedName("received_amount")
    val receivedAmount: Double,

    @SerializedName("change_amount")
    val changeAmount: Double,

    @SerializedName("payment_status")
    val paymentStatus: String,

    @SerializedName("notes")
    val notes: String?,

    @SerializedName("created_at")
    val createdAt: String,

    @SerializedName("processed_by_name")
    val processedByName: String
) : Parcelable

@Parcelize
data class PaymentOrderInfo(
    @SerializedName("id")
    val id: Int?,

    @SerializedName("order_number")
    val orderNumber: String,

    @SerializedName("table_name")
    val tableName: String,

    @SerializedName("total_amount")
    val totalAmount: Double,

    @SerializedName("status")
    val status: String
) : Parcelable

// Response wrappers
@Parcelize
data class OrdersResponse(
    @SerializedName("orders")
    val orders: List<Order>,
    @SerializedName("pagination")
    val pagination: Pagination
) : Parcelable

@Parcelize
data class CartItem(
    @SerializedName("menu_item")
    val menuItem: MenuItem,

    @SerializedName("quantity")
    val quantity: Int = 1,

    @SerializedName("notes")
    val notes: String? = null
) : Parcelable {

    val totalPrice: Double
        get() = menuItem.price * quantity

    val formattedTotalPrice: String
        get() = totalPrice.toVietnamCurrency()
}