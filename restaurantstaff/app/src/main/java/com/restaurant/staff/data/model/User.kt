package com.restaurant.staff.data.model

import android.os.Parcelable
import androidx.room.ColumnInfo
import androidx.room.Entity
import androidx.room.PrimaryKey
import com.google.gson.annotations.SerializedName
import com.google.gson.annotations.JsonAdapter
import kotlinx.parcelize.Parcelize
import com.restaurant.staff.data.adapters.BooleanTypeAdapter

@Parcelize
@Entity(tableName = "users")
data class User(
    @PrimaryKey
    @SerializedName("id")
    val id: Int,

    @SerializedName("username")
    val username: String,

    @ColumnInfo(name = "full_name")
    @SerializedName("full_name")
    val fullName: String,

    @SerializedName("email")
    val email: String?,

    @SerializedName("phone")
    val phone: String?,

    @SerializedName("role")
    val role: String, // admin, manager, waiter, kitchen

    @SerializedName("status")
    val status: String, // active, inactive

    @ColumnInfo(name = "created_at")
    @SerializedName("created_at")
    val createdAt: String,

    @ColumnInfo(name = "updated_at")
    @SerializedName("updated_at")
    val updatedAt: String
) : Parcelable

@Parcelize
data class LoginRequest(
    @SerializedName("username")
    val username: String,

    @SerializedName("password")
    val password: String
) : Parcelable

@Parcelize
data class LoginResponse(
    @SerializedName("user")
    val user: User,

    @SerializedName("token")
    val token: String
) : Parcelable

@Parcelize
data class ChangePasswordRequest(
    @SerializedName("current_password")
    val currentPassword: String,

    @SerializedName("new_password")
    val newPassword: String
) : Parcelable
