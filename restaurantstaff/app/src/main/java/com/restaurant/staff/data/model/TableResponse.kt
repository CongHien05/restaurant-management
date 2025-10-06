package com.restaurant.staff.data.model

import com.google.gson.annotations.SerializedName

data class TableResponse(
    @SerializedName("tables")
    val tables: List<Table>,

    @SerializedName("pagination")
    val pagination: Pagination? = null
)

// Using existing Pagination class from ApiResponse.kt
// But need to map the field names correctly
