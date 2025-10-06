package com.restaurant.staff.utils

import java.text.NumberFormat
import java.text.SimpleDateFormat
import java.util.*

/**
 * Extension functions for common operations
 */

// Currency formatting
fun Double.toVietnamCurrency(): String {
    return "${NumberFormat.getInstance(Locale.US).format(this.toInt())}đ"
}

fun Int.toVietnamCurrency(): String {
    return "${NumberFormat.getInstance(Locale.US).format(this)}đ"
}

// Date formatting
fun String.toDisplayDate(): String {
    return try {
        val inputFormat = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
        val outputFormat = SimpleDateFormat("dd/MM/yyyy HH:mm", Locale.getDefault())
        val date = inputFormat.parse(this)
        date?.let { outputFormat.format(it) } ?: this
    } catch (e: Exception) {
        this
    }
}

fun String.toDisplayTime(): String {
    return try {
        val inputFormat = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
        val outputFormat = SimpleDateFormat("HH:mm", Locale.getDefault())
        val date = inputFormat.parse(this)
        date?.let { outputFormat.format(it) } ?: this
    } catch (e: Exception) {
        this
    }
}

// String utilities
fun String?.isNotNullOrEmpty(): Boolean {
    return !this.isNullOrEmpty()
}

fun String?.orDefault(default: String): String {
    return if (this.isNullOrEmpty()) default else this
}

// View utilities
fun android.view.View.visible() {
    this.visibility = android.view.View.VISIBLE
}

fun android.view.View.gone() {
    this.visibility = android.view.View.GONE
}

fun android.view.View.invisible() {
    this.visibility = android.view.View.INVISIBLE
}

// Context utilities
//fun android.content.Context.showToast(message: String, duration: Int = android.widget.Toast.LENGTH_SHORT) {
//    android.widget.Toast.makeText(this, message, duration).show()
//}

// Collection utilities
fun <T> List<T>?.isNotNullOrEmpty(): Boolean {
    return !this.isNullOrEmpty()
}

// Status color utilities
fun String.getStatusColor(): Int {
    return when (this) {
        "available" -> android.graphics.Color.parseColor("#4CAF50")    // Green
        "occupied" -> android.graphics.Color.parseColor("#F44336")     // Red
        "reserved" -> android.graphics.Color.parseColor("#FF9800")     // Orange
        "cleaning" -> android.graphics.Color.parseColor("#2196F3")     // Blue
        "maintenance" -> android.graphics.Color.parseColor("#9E9E9E")  // Gray
        "draft" -> android.graphics.Color.parseColor("#9E9E9E")        // Gray
        "submitted" -> android.graphics.Color.parseColor("#2196F3")    // Blue
        "confirmed" -> android.graphics.Color.parseColor("#FF9800")    // Orange
        "preparing" -> android.graphics.Color.parseColor("#FF5722")    // Deep Orange
        "ready" -> android.graphics.Color.parseColor("#4CAF50")        // Green
        "served" -> android.graphics.Color.parseColor("#8BC34A")       // Light Green
        "paid" -> android.graphics.Color.parseColor("#795548")         // Brown
        "cancelled" -> android.graphics.Color.parseColor("#F44336")    // Red
        else -> android.graphics.Color.parseColor("#9E9E9E")           // Default Gray
    }
}
