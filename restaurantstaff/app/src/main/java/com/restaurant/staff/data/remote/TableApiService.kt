package com.restaurant.staff.data.remote

import com.restaurant.staff.data.model.*
import retrofit2.Response
import retrofit2.http.*

interface TableApiService {

    @GET("areas")
    suspend fun getAreas(): Response<ApiResponse<List<Area>>>

    @GET("areas/{id}/tables")
    suspend fun getTablesByArea(@Path("id") areaId: Int): Response<ApiResponse<TableWithArea>>

    @GET("tables")
    suspend fun getAllTables(): Response<ApiResponse<TableResponse>>

    @PUT("tables/{id}/status")
    suspend fun updateTableStatus(
        @Path("id") tableId: Int,
        @Body request: UpdateTableStatusRequest
    ): Response<ApiResponse<Table>>

    @GET("tables/{id}/current-order")
    suspend fun getCurrentOrder(@Path("id") tableId: Int): Response<ApiResponse<CurrentOrderPayload>>

    @GET("tables/{id}/details")
    suspend fun getTableDetails(@Path("id") tableId: Int): Response<ApiResponse<TableDetailsPayload>>

    @PUT("tables/{id}/order-info")
    suspend fun updateTableOrderInfo(
        @Path("id") tableId: Int,
        @Body request: UpdateTableOrderInfoRequest
    ): Response<ApiResponse<Unit>>
}
