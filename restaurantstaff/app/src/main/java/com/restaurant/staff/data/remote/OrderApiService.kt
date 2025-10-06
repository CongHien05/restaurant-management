package com.restaurant.staff.data.remote

import com.restaurant.staff.data.model.*
import retrofit2.Response
import retrofit2.http.*

interface OrderApiService {

    @GET("orders")
    suspend fun getOrders(
        @Query("page") page: Int = 1,
        @Query("limit") limit: Int = 20,
        @Query("status") status: String? = null,
        @Query("table_id") tableId: Int? = null
    ): Response<ApiResponse<OrdersResponse>>

    @POST("orders")
    suspend fun createOrder(@Body request: CreateOrderRequest): Response<ApiResponse<Order>>

    @GET("orders/{id}")
    suspend fun getOrderDetails(@Path("id") orderId: Int): Response<ApiResponse<Order>>

    @PUT("orders/{id}")
    suspend fun updateOrder(
        @Path("id") orderId: Int,
        @Body request: UpdateOrderRequest
    ): Response<ApiResponse<Order>>

    @PUT("orders/{id}/submit")
    suspend fun submitOrder(@Path("id") orderId: Int): Response<ApiResponse<Unit>>

    @GET("orders/my-orders")
    suspend fun getMyOrders(
        @Query("date") date: String? = null,
        @Query("page") page: Int = 1,
        @Query("limit") limit: Int = 20
    ): Response<ApiResponse<Map<String, Any>>>

    // Order Items
    @POST("orders/{id}/items")
    suspend fun addOrderItem(
        @Path("id") orderId: Int,
        @Body request: AddOrderItemRequest
    ): Response<ApiResponse<OrderItem>>

    @PUT("orders/{orderId}/items/{id}")
    suspend fun updateOrderItem(
        @Path("orderId") orderId: Int,
        @Path("id") orderItemId: Int,
        @Body request: UpdateOrderItemRequest
    ): Response<ApiResponse<OrderItem>>

    @DELETE("orders/{orderId}/items/{id}")
    suspend fun deleteOrderItem(
        @Path("orderId") orderId: Int,
        @Path("id") orderItemId: Int
    ): Response<ApiResponse<Unit>>

    // Payment
    @POST("orders/{id}/payment")
    suspend fun processPayment(
        @Path("id") orderId: Int,
        @Body request: PaymentRequest
    ): Response<ApiResponse<PaymentResponse>>
}
