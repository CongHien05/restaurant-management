package com.restaurant.staff.data.model

import android.os.Parcelable
import com.restaurant.staff.utils.toVietnamCurrency
import androidx.room.ColumnInfo
import androidx.room.Entity
import androidx.room.PrimaryKey
import com.google.gson.annotations.SerializedName
import com.google.gson.annotations.JsonAdapter
import kotlinx.parcelize.Parcelize
import com.restaurant.staff.data.adapters.BooleanTypeAdapter

@Parcelize
@Entity(tableName = "categories")
data class Category(
    @PrimaryKey
    @SerializedName("id")
    val id: Int,

    @SerializedName("name")
    val name: String,

    @SerializedName("description")
    val description: String?,

    @SerializedName("image")
    val image: String?,

    @SerializedName("status")
    val status: String, // active, inactive

    @ColumnInfo(name = "sort_order")
    @SerializedName("sort_order")
    val sortOrder: Int,

    @ColumnInfo(name = "created_at")
    @SerializedName("created_at")
    val createdAt: String,

    @ColumnInfo(name = "updated_at")
    @SerializedName("updated_at")
    val updatedAt: String
) : Parcelable

@Parcelize
@Entity(tableName = "products")
data class MenuItem(
    @PrimaryKey
    @SerializedName("id")
    val id: Int,

    @SerializedName("name")
    val name: String,

    @SerializedName("description")
    val description: String?,

    @SerializedName("price")
    val price: Double,

    @ColumnInfo(name = "category_id")
    @SerializedName("category_id")
    val categoryId: Int,

    @SerializedName("image")
    val image: String?,

    @SerializedName("status")
    val status: String, // active, inactive, out_of_stock

    @ColumnInfo(name = "sort_order")
    @SerializedName("sort_order")
    val sortOrder: Int,

    @ColumnInfo(name = "created_at")
    @SerializedName("created_at")
    val createdAt: String,

    @ColumnInfo(name = "updated_at")
    @SerializedName("updated_at")
    val updatedAt: String
) : Parcelable {

    val formattedPrice: String
        get() = price.toVietnamCurrency()

    val isAvailable: Boolean
        get() = status == "active"
}

@Parcelize
data class CategoryWithItems(
    @SerializedName("category")
    val category: Category,

    @SerializedName("items")
    val items: List<MenuItem>
) : Parcelable

@Parcelize
data class MenuSearchResult(
    @SerializedName("query")
    val query: String,

    @SerializedName("category_id")
    val categoryId: Int?,

    @SerializedName("results")
    val results: List<MenuItem>,

    @SerializedName("count")
    val count: Int
) : Parcelable

