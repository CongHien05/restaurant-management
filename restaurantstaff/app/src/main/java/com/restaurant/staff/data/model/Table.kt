package com.restaurant.staff.data.model

import android.os.Parcelable
import androidx.room.ColumnInfo
import androidx.room.Entity
import androidx.room.PrimaryKey
import com.google.gson.annotations.SerializedName
import kotlinx.parcelize.Parcelize

@Parcelize
@Entity(tableName = "tables")
data class Table(
    @PrimaryKey
    @SerializedName("id")
    val id: Int,

    @SerializedName("name")
    val name: String,

    @ColumnInfo(name = "area_id")
    @SerializedName("area_id")
    val areaId: Int,

    @SerializedName("capacity")
    val capacity: Int,

    @SerializedName("status")
    val status: String, // available, occupied, reserved, maintenance

    @ColumnInfo(name = "position_x")
    @SerializedName("position_x")
    val positionX: Int = 0,

    @ColumnInfo(name = "position_y")
    @SerializedName("position_y")
    val positionY: Int = 0,

    @ColumnInfo(name = "created_at")
    @SerializedName("created_at")
    val createdAt: String,

    @ColumnInfo(name = "updated_at")
    @SerializedName("updated_at")
    val updatedAt: String,

    // Additional fields for order management (not in database)
    @SerializedName("table_number")
    val tableNumber: String? = null,

    @SerializedName("table_name")
    val tableName: String? = null,

    @SerializedName("qr_code")
    val qrCode: String? = null,

    @SerializedName("current_order_id")
    val currentOrderId: Int? = null,

    @SerializedName("order_number")
    val orderNumber: String? = null,

    @SerializedName("order_status")
    val orderStatus: String? = null,

    @SerializedName("customer_count")
    val customerCount: Int? = null,

    @SerializedName("total_amount")
    val totalAmount: Double? = null,

    @SerializedName("order_created_at")
    val orderCreatedAt: String? = null,

    @SerializedName("waiter_name")
    val waiterName: String? = null,

    @SerializedName("area_name")
    val areaName: String? = null,

    @SerializedName("pending_amount")
    val pendingAmount: Double? = null,

    @SerializedName("active_orders")
    val activeOrders: Int? = null
) : Parcelable {

    val statusColor: Int
        get() = when (status) {
            "available" -> android.graphics.Color.parseColor("#4CAF50") // Green
            "occupied" -> android.graphics.Color.parseColor("#F44336")   // Red
            "reserved" -> android.graphics.Color.parseColor("#FF9800")   // Orange
            "maintenance" -> android.graphics.Color.parseColor("#9E9E9E") // Gray
            else -> android.graphics.Color.parseColor("#9E9E9E")
        }

    val statusText: String
        get() = when (status) {
            "available" -> "Trống"
            "occupied" -> "Có khách"
            "reserved" -> "Đã đặt"
            "maintenance" -> "Bảo trì"
            else -> status
        }
}

@Parcelize
data class UpdateTableStatusRequest(
    @SerializedName("status")
    val status: String
) : Parcelable

@Parcelize
data class UpdateTableOrderInfoRequest(
    @SerializedName("order_id")
    val orderId: Int,

    @SerializedName("total_amount")
    val totalAmount: Double
) : Parcelable

@Parcelize
data class TableWithArea(
    @SerializedName("area")
    val area: Area,

    @SerializedName("tables")
    val tables: List<Table>
) : Parcelable
