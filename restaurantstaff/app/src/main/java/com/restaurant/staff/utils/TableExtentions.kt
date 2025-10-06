package com.restaurant.staff.utils

import com.restaurant.staff.data.model.Table
import java.text.NumberFormat
import java.text.SimpleDateFormat
import java.util.*

/**
 * Extension functions for Table model
 */

// Format display time from orderCreatedAt
val Table.displayOrderTime: String
    get() = try {
        if (orderCreatedAt.isNullOrEmpty()) "N/A"
        else {
            val inputFormat = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
            val outputFormat = SimpleDateFormat("HH:mm", Locale.getDefault())
            val date = inputFormat.parse(orderCreatedAt)
            outputFormat.format(date ?: Date())
        }
    } catch (e: Exception) {
        orderCreatedAt ?: "N/A"
    }

// Format currency
val Table.displayTotalAmount: String
    get() = try {
        val amount = (pendingAmount ?: 0.0).toInt()
        NumberFormat.getNumberInstance(Locale("vi", "VN")).format(amount) + " VND"
    } catch (e: Exception) {
        "0 VND"
    }

// Get area display name (you can enhance this with actual area mapping later)
val Table.displayAreaName: String
    get() = areaName ?: when (areaId) {
        1 -> "Tầng trệt"
        2 -> "Sân thượng"
        3 -> "Phòng VIP"
        else -> "Khu vực $areaId"
    }

// Check if table has active order
val Table.hasActiveOrder: Boolean
    get() = (activeOrders ?: 0) > 0 || (pendingAmount ?: 0.0) > 0
