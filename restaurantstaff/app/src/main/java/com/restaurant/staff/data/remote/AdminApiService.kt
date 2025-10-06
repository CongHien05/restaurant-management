package com.restaurant.staff.data.remote

import com.restaurant.staff.data.model.*
import retrofit2.Response
import retrofit2.http.*

interface AdminApiService {

    @GET("admin/dashboard")
    suspend fun getDashboard(): Response<ApiResponse<Map<String, Any>>>

    @GET("kitchen/pending-orders")
    suspend fun getPendingOrders(): Response<ApiResponse<List<Order>>>

    @PUT("kitchen/orders/{id}/status")
    suspend fun updateOrderStatus(
        @Path("id") orderId: Int,
        @Body status: Map<String, String>
    ): Response<ApiResponse<Map<String, Any>>>

    @PUT("kitchen/order-items/{id}/status")
    suspend fun updateOrderItemStatus(
        @Path("id") orderItemId: Int,
        @Body status: Map<String, String>
    ): Response<ApiResponse<Map<String, Any>>>
}
